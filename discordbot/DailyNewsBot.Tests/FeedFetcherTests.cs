using System.Net;
using DailyNewsBot.Processing;
using Xunit;

namespace DailyNewsBot.Tests;

public class FeedFetcherTests
{
    [Theory]
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
    public void IsPrivateAddress_IPv6Public_ReturnsFalse()
    {
        // 2001:4860:4860::8888 = Google DNS IPv6
        Assert.False(FeedFetcher.IsPrivateAddress(IPAddress.Parse("2001:4860:4860::8888")));
    }
}
