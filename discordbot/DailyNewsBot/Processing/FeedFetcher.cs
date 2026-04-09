using System.Net;
using System.Net.Sockets;
using CodeHollow.FeedReader;
using DailyNewsBot.Models;
using Microsoft.Extensions.Http;

namespace DailyNewsBot.Processing;

public class FeedFetcher
{
    private readonly IHttpClientFactory _httpClientFactory;
    private readonly ILogger<FeedFetcher> _logger;

    public FeedFetcher(IHttpClientFactory httpClientFactory, ILogger<FeedFetcher> logger)
    {
        _httpClientFactory = httpClientFactory;
        _logger = logger;
    }

    /// <summary>
    /// Holt Artikel aus einem RSS/Atom-Feed.
    /// Gibt leere Liste zurück wenn Feed nicht erreichbar oder ungültig.
    /// </summary>
    public async Task<List<ProcessedArticle>> FetchArticlesAsync(
        FeedConfig feedConfig,
        string channelId,
        HashSet<string> seenHashes,
        CancellationToken ct = default)
    {
        try
        {
            if (await IsPrivateHostAsync(feedConfig.Url))
            {
                _logger.LogWarning("SSRF-Block: {Url}", feedConfig.Url);
                return [];
            }

            var client = _httpClientFactory.CreateClient("feeds");
            using var cts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            cts.CancelAfter(TimeSpan.FromSeconds(10));

            var content = await client.GetStringAsync(feedConfig.Url, cts.Token);
            var feed = FeedReader.ReadFromString(content);

            var articles = new List<ProcessedArticle>();
            var count = 0;

            foreach (var item in feed.Items)
            {
                if (count >= feedConfig.MaxItems) break;

                var url = item.Link ?? "";
                if (string.IsNullOrWhiteSpace(url)) continue;

                var urlHash = ComputeUrlHash(url);
                if (seenHashes.Contains(urlHash)) continue;

                var title   = TextProcessor.ProcessTitle(item.Title ?? "");
                var summary = TextProcessor.ProcessSummary(item.Description ?? item.Content ?? "");

                if (string.IsNullOrWhiteSpace(title)) continue;

                articles.Add(new ProcessedArticle(title, url, summary, urlHash, feedConfig.Name));
                count++;
            }

            return articles;
        }
        catch (OperationCanceledException)
        {
            _logger.LogWarning("Feed-Timeout: {Url}", feedConfig.Url);
            return [];
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Feed-Fehler: {Url}", feedConfig.Url);
            return [];
        }
    }

    private static readonly HashSet<string> _trackingParams = new(StringComparer.OrdinalIgnoreCase)
    {
        // Google Analytics / Ads
        "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content", "utm_id",
        "gclid", "gclsrc", "dclid",
        // Social / Ad networks
        "fbclid", "msclkid", "twclid", "li_fat_id", "igshid",
        // Email marketing
        "mc_cid", "mc_eid", "mkt_tok", "_hsenc", "_hsmi", "nr_email_referer",
        // Generic referral / source tracking
        "ref", "referrer", "source", "via", "cmp", "cmpid",
        // Feed / publisher specific
        "ftag", "xtor", "s_cid",
    };

    /// <summary>
    /// Normalisiert eine URL für Deduplication: entfernt Tracking-Parameter und Fragment.
    /// Der angezeigte/gespeicherte URL bleibt der Original-URL.
    /// </summary>
    private static string NormalizeUrl(string url)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri))
            return url;

        if (string.IsNullOrEmpty(uri.Query))
            return $"{uri.Scheme}://{uri.Authority}{uri.AbsolutePath}";

        var kept = uri.Query.TrimStart('?')
            .Split('&', StringSplitOptions.RemoveEmptyEntries)
            .Where(p => !_trackingParams.Contains(p.Split('=')[0]))
            .ToArray();

        var qs = kept.Length > 0 ? "?" + string.Join("&", kept) : "";
        return $"{uri.Scheme}://{uri.Authority}{uri.AbsolutePath}{qs}";
    }

    private static string ComputeUrlHash(string url)
    {
        var bytes = System.Security.Cryptography.SHA256.HashData(
            System.Text.Encoding.UTF8.GetBytes(NormalizeUrl(url)));
        return Convert.ToHexString(bytes).ToLowerInvariant();
    }

    private static async Task<bool> IsPrivateHostAsync(string url)
    {
        try
        {
            var host = new Uri(url).Host;
            if (IPAddress.TryParse(host, out var literal))
                return IsPrivateAddress(literal);
            var addresses = await Dns.GetHostAddressesAsync(host);
            return addresses.Length == 0 || addresses.Any(IsPrivateAddress);
        }
        catch
        {
            return true; // DNS-Fehler = blockieren
        }
    }

    public static bool IsPrivateAddress(IPAddress addr)
    {
        if (addr.IsIPv4MappedToIPv6)
            return IsPrivateAddress(addr.MapToIPv4());
        if (addr.Equals(IPAddress.IPv6Loopback)) return true;
        if (addr.AddressFamily != AddressFamily.InterNetwork)
        {
            // IPv6: block ULA (fc00::/7) and link-local (fe80::/10)
            var bytes = addr.GetAddressBytes();
            if (bytes.Length == 16)
            {
                if ((bytes[0] & 0xFE) == 0xFC) return true; // fc00::/7 ULA
                if (bytes[0] == 0xFE && (bytes[1] & 0xC0) == 0x80) return true; // fe80::/10 link-local
            }
            return false;
        }
        var b = addr.GetAddressBytes();
        return b[0] == 0                                       // 0.0.0.0/8 unspecified
            || b[0] == 127                                     // 127.0.0.0/8 loopback
            || b[0] == 10                                      // 10.0.0.0/8 private A
            || (b[0] == 172 && b[1] is >= 16 and <= 31)        // 172.16.0.0/12 private B
            || (b[0] == 192 && b[1] == 168)                    // 192.168.0.0/16 private C
            || (b[0] == 169 && b[1] == 254);                   // 169.254.0.0/16 link-local
    }
}
