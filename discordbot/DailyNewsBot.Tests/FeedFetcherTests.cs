using System.Net;
using DailyNewsBot.Processing;
using Microsoft.Extensions.Logging;
using NSubstitute;
using Xunit;

namespace DailyNewsBot.Tests;

public class FeedFetcherTests
{
    [Theory]
    [InlineData("0.0.0.0",         true)]   // unspecified address
    [InlineData("127.0.0.1",       true)]   // loopback
    [InlineData("127.0.0.50",      true)]   // loopback range
    [InlineData("10.0.0.1",        true)]   // private A
    [InlineData("10.255.255.255",  true)]   // private A edge
    [InlineData("172.16.0.1",      true)]   // private B start
    [InlineData("172.31.255.255",  true)]   // private B end
    [InlineData("172.15.255.255",  false)]  // just below B
    [InlineData("172.32.0.0",      false)]  // just above B
    [InlineData("192.168.1.1",     true)]   // private C
    [InlineData("169.254.0.1",     true)]   // link-local
    [InlineData("8.8.8.8",         false)]  // public DNS
    [InlineData("1.1.1.1",         false)]  // public DNS
    [InlineData("93.184.216.34",   false)]  // example.com
    public void IsPrivateAddress_IPv4(string ip, bool expected)
    {
        Assert.Equal(expected, FeedFetcher.IsPrivateAddress(IPAddress.Parse(ip)));
    }

    [Fact]
    public void IsPrivateAddress_IPv6Loopback_ReturnsTrue()
    {
        Assert.True(FeedFetcher.IsPrivateAddress(IPAddress.IPv6Loopback));
    }

    [Fact]
    public void IsPrivateAddress_IPv6UniqueLocal_ReturnsTrue()
    {
        // fc00::/7 ULA — IPv6 equivalent of RFC 1918 private space
        Assert.True(FeedFetcher.IsPrivateAddress(IPAddress.Parse("fd12:3456:789a::1")));
    }

    [Fact]
    public void IsPrivateAddress_IPv6LinkLocal_ReturnsTrue()
    {
        // fe80::/10 — IPv6 link-local, equivalent of 169.254.0.0/16
        Assert.True(FeedFetcher.IsPrivateAddress(IPAddress.Parse("fe80::1")));
    }

    [Fact]
    public void IsPrivateAddress_IPv6Public_ReturnsFalse()
    {
        // 2001:4860:4860::8888 = Google DNS IPv6
        Assert.False(FeedFetcher.IsPrivateAddress(IPAddress.Parse("2001:4860:4860::8888")));
    }

    [Fact]
    public void IsPrivateAddress_IPv4MappedIPv6Loopback_ReturnsTrue()
    {
        // ::ffff:127.0.0.1 — IPv4-mapped form of loopback
        var mapped = IPAddress.Parse("127.0.0.1").MapToIPv6();
        Assert.True(FeedFetcher.IsPrivateAddress(mapped));
    }

    // ── NormalizeUrl ────────────────────────────────────────────────────────────

    [Theory]
    [InlineData("https://example.com/article?utm_source=rss",
                "https://example.com/article")]
    [InlineData("https://example.com/article?utm_source=rss&utm_medium=feed&page=2",
                "https://example.com/article?page=2")]
    [InlineData("https://example.com/article?page=2&ref=social",
                "https://example.com/article?page=2")]
    [InlineData("https://example.com/article?fbclid=abc&gclid=xyz",
                "https://example.com/article")]
    [InlineData("https://example.com/article",
                "https://example.com/article")]
    public void NormalizeUrl_TrackingParams_AreRemoved(string input, string expected)
    {
        Assert.Equal(expected, FeedFetcher.NormalizeUrl(input));
    }

    [Fact]
    public void NormalizeUrl_NonTrackingParams_AreKept()
    {
        Assert.Equal(
            "https://example.com/article?id=42&page=3",
            FeedFetcher.NormalizeUrl("https://example.com/article?id=42&page=3"));
    }

    [Fact]
    public void NormalizeUrl_InvalidUrl_ReturnsOriginal()
    {
        var invalid = "not a valid url";
        Assert.Equal(invalid, FeedFetcher.NormalizeUrl(invalid));
    }

    // ── ComputeUrlHash ───────────────────────────────────────────────────────────

    [Fact]
    public void ComputeUrlHash_SameUrlDifferentTrackingParams_ReturnsSameHash()
    {
        var hash1 = FeedFetcher.ComputeUrlHash("https://example.com/article?utm_source=twitter");
        var hash2 = FeedFetcher.ComputeUrlHash("https://example.com/article?fbclid=abc123");
        Assert.Equal(hash1, hash2);
        Assert.Matches(@"^[0-9a-f]{64}$", hash1);
    }

    [Fact]
    public void ComputeUrlHash_DifferentUrls_ReturnsDifferentHash()
    {
        var hash1 = FeedFetcher.ComputeUrlHash("https://example.com/article-1");
        var hash2 = FeedFetcher.ComputeUrlHash("https://example.com/article-2");
        Assert.NotEqual(hash1, hash2);
    }

    // ── FetchArticlesAsync ───────────────────────────────────────────────────────

    private const string ValidRssXml = """
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
          <channel>
            <title>Test Feed</title>
            <link>https://example.com</link>
            <item>
              <title>Artikel Eins</title>
              <link>https://example.com/artikel-1</link>
              <description>Erste Beschreibung</description>
            </item>
            <item>
              <title>Artikel Zwei</title>
              <link>https://example.com/artikel-2?utm_source=rss</link>
              <description>Zweite Beschreibung</description>
            </item>
          </channel>
        </rss>
        """;

    private static IHttpClientFactory CreateHttpFactory(string content)
    {
        var handler = new FakeHttpHandler(content);
        var client = new HttpClient(handler);
        var factory = Substitute.For<IHttpClientFactory>();
        factory.CreateClient(Arg.Any<string>()).Returns(client);
        return factory;
    }

    private class FakeHttpHandler : HttpMessageHandler
    {
        private readonly string _content;
        public FakeHttpHandler(string content) => _content = content;
        protected override Task<HttpResponseMessage> SendAsync(
            HttpRequestMessage r, CancellationToken ct)
            => Task.FromResult(new HttpResponseMessage(HttpStatusCode.OK)
               { Content = new StringContent(_content) });
    }

    private class TimeoutHttpHandler : HttpMessageHandler
    {
        protected override Task<HttpResponseMessage> SendAsync(
            HttpRequestMessage r, CancellationToken ct)
            => throw new TaskCanceledException("Simulated timeout");
    }

    [Fact]
    public async Task FetchArticlesAsync_ValidRss_ReturnsAllArticles()
    {
        var factory = CreateHttpFactory(ValidRssXml);
        var logger = Substitute.For<ILogger<FeedFetcher>>();
        var fetcher = new FeedFetcher(factory, logger);
        var config = new DailyNewsBot.Models.FeedConfig("Test", "https://example.com/feed", 10);

        var articles = await fetcher.FetchArticlesAsync(config, "channel1", [], CancellationToken.None);

        Assert.Equal(2, articles.Count);
        Assert.Equal("Artikel Eins", articles[0].Title);
    }

    [Fact]
    public async Task FetchArticlesAsync_KnownHash_IsSkipped()
    {
        var factory = CreateHttpFactory(ValidRssXml);
        var logger = Substitute.For<ILogger<FeedFetcher>>();
        var fetcher = new FeedFetcher(factory, logger);
        var config = new DailyNewsBot.Models.FeedConfig("Test", "https://example.com/feed", 10);
        var knownHash = FeedFetcher.ComputeUrlHash("https://example.com/artikel-1");

        var articles = await fetcher.FetchArticlesAsync(config, "channel1", [knownHash], CancellationToken.None);

        Assert.Single(articles);
        Assert.Equal("Artikel Zwei", articles[0].Title);
    }

    [Fact]
    public async Task FetchArticlesAsync_PrivateIp_ReturnsEmpty()
    {
        var factory = Substitute.For<IHttpClientFactory>();
        var logger = Substitute.For<ILogger<FeedFetcher>>();
        var fetcher = new FeedFetcher(factory, logger);
        var config = new DailyNewsBot.Models.FeedConfig("Test", "http://127.0.0.1/feed", 10);

        var articles = await fetcher.FetchArticlesAsync(config, "channel1", [], CancellationToken.None);

        Assert.Empty(articles);
    }

    [Fact]
    public async Task FetchArticlesAsync_Timeout_ReturnsEmpty()
    {
        var client = new HttpClient(new TimeoutHttpHandler());
        var factory = Substitute.For<IHttpClientFactory>();
        factory.CreateClient(Arg.Any<string>()).Returns(client);
        var logger = Substitute.For<ILogger<FeedFetcher>>();
        var fetcher = new FeedFetcher(factory, logger);
        var config = new DailyNewsBot.Models.FeedConfig("Test", "https://example.com/feed", 10);

        var articles = await fetcher.FetchArticlesAsync(config, "channel1", [], CancellationToken.None);

        Assert.Empty(articles);
    }
}
