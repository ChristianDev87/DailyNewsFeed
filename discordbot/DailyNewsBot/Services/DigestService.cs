using Dapper;
using DailyNewsBot.Data;
using DailyNewsBot.Models;
using DailyNewsBot.Processing;
using Discord;
using Discord.Rest;
using System.Text;

namespace DailyNewsBot.Services;

public class DigestService
{
    private readonly IDatabase _db;
    private readonly FeedFetcher _feedFetcher;
    private readonly ILogger<DigestService> _logger;
    private readonly int _maxParallelFeeds;
    private readonly TimeSpan _categoryDelay;
    private readonly TimeSpan _channelDelay;

    private static readonly TimeZoneInfo _tz =
        TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin");

    private static readonly TimeSpan InterChunkDelay = TimeSpan.FromSeconds(2);

    private static DateTime NowBerlin() =>
        TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, _tz);


    public DigestService(
        IDatabase db,
        FeedFetcher feedFetcher,
        ILogger<DigestService> logger,
        IConfiguration config)
    {
        _db = db;
        _feedFetcher = feedFetcher;
        _logger = logger;
        _maxParallelFeeds = int.TryParse(config["MAX_PARALLEL_FEEDS"], out var n) ? Math.Max(1, n) : 10;
        var catSec  = int.TryParse(config["CATEGORY_SEND_DELAY_SECONDS"], out var c)  ? c  : 2;
        _categoryDelay = TimeSpan.FromSeconds(Math.Max(2, catSec));
        var chanSec = int.TryParse(config["CHANNEL_SEND_DELAY_SECONDS"],  out var ch) ? ch : 5;
        _channelDelay  = TimeSpan.FromSeconds(Math.Max(5, chanSec));
    }

    /// <summary>
    /// Führt den Digest für alle aktiven Kanäle aus.
    /// Ein fehlgeschlagener Kanal stoppt nicht die anderen.
    /// </summary>
    public async Task RunAllChannelsAsync(IBotClientProvider clientProvider, CancellationToken ct)
    {
        var channelIds = await GetActiveChannelIdsAsync(ct);
        _logger.LogInformation("Digest-Lauf für {Count} aktive Kanäle", channelIds.Count);

        for (int i = 0; i < channelIds.Count; i++)
        {
            if (ct.IsCancellationRequested) break;

            try
            {
                await RunSingleChannelAsync(channelIds[i], clientProvider, ct);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Digest fehlgeschlagen für Kanal {ChannelId}", channelIds[i]);
            }

            if (i < channelIds.Count - 1)
                await Task.Delay(_channelDelay, ct);
        }
    }

    /// <summary>
    /// Führt den Digest für einen einzelnen Kanal aus.
    /// </summary>
    public async Task RunSingleChannelAsync(
        string channelId,
        IBotClientProvider clientProvider,
        CancellationToken ct)
    {
        await using var conn = await _db.GetOpenConnectionAsync(ct);
        await using var tx   = await conn.BeginTransactionAsync(ct);

        var channel = await conn.QueryFirstOrDefaultAsync<Channel>(
            "SELECT * FROM channels WHERE channel_id = @channelId AND active = 1 FOR UPDATE",
            new { channelId }, tx);

        if (channel is null)
        {
            _logger.LogWarning("Kanal {ChannelId} nicht aktiv oder nicht gefunden", channelId);
            await tx.RollbackAsync(ct);
            return;
        }

        var seenHashes = (await conn.QueryAsync<string>(
            "SELECT url_hash FROM seen_articles WHERE channel_id = @channelId",
            new { channelId }, tx)).ToHashSet();

        await tx.CommitAsync(ct);

        _logger.LogInformation("Kanal {ChannelId}: {SeenCount} bereits gesehene Artikel in DB", channelId, seenHashes.Count);

        var categories = await GetCategoriesForChannelAsync(channelId, ct);

        if (!categories.Any())
        {
            _logger.LogInformation("Kanal {ChannelId}: keine Feeds konfiguriert — übersprungen", channelId);
            return;
        }

        var seenHashesLock = new object();
        using var sem = new SemaphoreSlim(_maxParallelFeeds);

        var tasks = categories.Select(async cat =>
        {
            var catArticles = new List<ProcessedArticle>();
            foreach (var feed in cat.Feeds)
            {
                await sem.WaitAsync(ct);
                try
                {
                    HashSet<string> snapshot;
                    lock (seenHashesLock) { snapshot = [..seenHashes]; }

                    var articles = await _feedFetcher.FetchArticlesAsync(feed, channelId, snapshot, ct);
                    catArticles.AddRange(articles);

                    lock (seenHashesLock)
                    {
                        foreach (var a in articles) seenHashes.Add(a.UrlHash);
                    }
                }
                finally { sem.Release(); }
            }
            return (cat, catArticles);
        });

        var results = await Task.WhenAll(tasks);
        var allNew  = results.Where(r => r.catArticles.Any()).ToList();

        if (!allNew.Any())
        {
            _logger.LogInformation("Kanal {ChannelId}: keine neuen Artikel — stiller Lauf", channelId);
            return;
        }

        var restClient  = clientProvider.GetRestClientForChannel(channelId);
        var threadId    = await GetOrCreateThreadAsync(channelId, restClient, ct);

        if (threadId == 0)
        {
            _logger.LogError("Thread für Kanal {ChannelId} konnte nicht erstellt werden", channelId);
            return;
        }

        var threadChannel = await restClient.GetChannelAsync(threadId) as ITextChannel;
        if (threadChannel is null)
        {
            _logger.LogError("Thread {ThreadId} nicht erreichbar", threadId);
            return;
        }

        await SendWithRateLimitAsync(threadChannel, BuildHeaderText(NowBerlin()), ct);

        int totalSent = 0;
        for (int i = 0; i < allNew.Count; i++)
        {
            var (cat, catArticles) = allNew[i];
            var chunks = ChunkBuilder.BuildChunks(BuildCategoryText(cat, catArticles));

            for (int j = 0; j < chunks.Count; j++)
            {
                await SendWithRateLimitAsync(threadChannel, chunks[j], ct);
                if (j < chunks.Count - 1)
                    await Task.Delay(InterChunkDelay, ct);
            }

            await BulkInsertSeenArticlesAsync(catArticles, channelId);
            totalSent += catArticles.Count;

            if (i < allNew.Count - 1)
                await Task.Delay(_categoryDelay, ct);
        }

        _logger.LogInformation(
            "Digest für Kanal {ChannelId}: {Count} neue Artikel gesendet",
            channelId, totalSent);
    }

    public static bool IsFirstRunToday(DateTime berlinNow) =>
        berlinNow.Hour is >= 0 and < 4;

    internal static string BuildHeaderText(DateTime berlinNow) =>
        IsFirstRunToday(berlinNow)
            ? $"📰 **News-Digest — {berlinNow:dd.MM.yyyy}**"
            : $"🔄 **Update — {berlinNow:HH:mm} Uhr**";

    internal static string BuildCategoryText(CategoryData cat, List<ProcessedArticle> articles)
    {
        var sb = new StringBuilder();
        sb.AppendLine($"{cat.Emoji} {cat.Label}");
        sb.AppendLine("────────────────────────────────");
        sb.AppendLine();

        foreach (var article in articles)
        {
            sb.AppendLine($"🔹 **{article.Title}**");
            if (!string.IsNullOrWhiteSpace(article.Summary))
                sb.AppendLine(article.Summary);
            sb.AppendLine($"<{article.Url}>");
            sb.AppendLine();
        }

        return sb.ToString().TrimEnd();
    }

    private async Task<ulong> GetOrCreateThreadAsync(
        string channelId, DiscordRestClient restClient, CancellationToken ct)
    {
        var today = NowBerlin().Date;

        // Bestehenden Thread suchen
        await using var conn = await _db.GetOpenConnectionAsync(ct);

        var existingThreadId = await conn.ExecuteScalarAsync<string?>(
            "SELECT thread_id FROM daily_threads WHERE date = @date AND channel_id = @channelId",
            new { date = today, channelId });

        if (existingThreadId is not null && ulong.TryParse(existingThreadId, out var tid))
            return tid;

        // Neuen Thread erstellen
        if (!ulong.TryParse(channelId, out var chanId))
        {
            _logger.LogError("Ungültige channel_id: {ChannelId}", channelId);
            return 0;
        }

        var textChannel = await restClient.GetChannelAsync(chanId) as ITextChannel;
        if (textChannel is null)
        {
            _logger.LogError("Kanal {ChannelId} nicht gefunden oder kein Text-Kanal", channelId);
            return 0;
        }

        var thread = await textChannel.CreateThreadAsync(
            name: $"🔔 Daily News — {NowBerlin():dd.MM.yyyy}",
            autoArchiveDuration: ThreadArchiveDuration.OneWeek,
            type: ThreadType.PublicThread);

        // Thread-ID speichern
        await conn.ExecuteAsync(
            "INSERT IGNORE INTO daily_threads (date, channel_id, thread_id, created_at) " +
            "VALUES (@date, @channelId, @threadId, NOW())",
            new { date = today, channelId, threadId = thread.Id.ToString() });

        return thread.Id;
    }

    private async Task SendWithRateLimitAsync(
        ITextChannel channel, string content, CancellationToken ct)
    {
        while (true)
        {
            try
            {
                await channel.SendMessageAsync(content, flags: MessageFlags.SuppressEmbeds);
                return;
            }
            catch (Discord.Net.HttpException ex) when ((int)ex.HttpCode == 429)
            {
                // Discord.Net IRequest does not expose response headers directly;
                // fall back to a conservative fixed delay.
                const double delay = 5.0;
                _logger.LogWarning("Discord Rate Limit — warte {Delay}s (Reason: {Reason})", delay, ex.Reason);
                await Task.Delay(TimeSpan.FromSeconds(delay), ct);
            }
        }
    }

    private async Task<List<string>> GetActiveChannelIdsAsync(CancellationToken ct = default)
    {
        await using var conn = await _db.GetOpenConnectionAsync(ct);
        return (await conn.QueryAsync<string>(
            "SELECT channel_id FROM channels WHERE active = 1")).ToList();
    }

    private async Task<List<CategoryData>> GetCategoriesForChannelAsync(string channelId, CancellationToken ct = default)
    {
        await using var conn = await _db.GetOpenConnectionAsync(ct);

        var categories = (await conn.QueryAsync<Category>(
            "SELECT * FROM channel_categories WHERE channel_id = @channelId AND active = 1 ORDER BY position",
            new { channelId })).ToList();

        if (!categories.Any())
            return [];

        var result = new List<CategoryData>();
        foreach (var cat in categories)
        {
            var feeds = (await conn.QueryAsync<Feed>(
                "SELECT * FROM channel_feeds WHERE category_id = @id AND active = 1",
                new { id = cat.Id })).ToList();
            result.Add(new CategoryData(
                cat.Label, cat.Emoji,
                feeds.Select(f => new FeedConfig(f.Name, f.Url, f.MaxItems)).ToList()));
        }

        return result;
    }

    private async Task BulkInsertSeenArticlesAsync(List<ProcessedArticle> articles, string channelId)
    {
        if (!articles.Any()) return;

        await using var conn = await _db.GetOpenConnectionAsync();

        await conn.ExecuteAsync(
            "INSERT IGNORE INTO seen_articles (url_hash, channel_id, url, title, source, seen_at) " +
            "VALUES (@UrlHash, @ChannelId, @Url, @Title, @Source, NOW())",
            articles.Select(a => new
            {
                a.UrlHash,
                ChannelId = channelId,
                a.Url,
                a.Title,
                a.Source,
            }));
    }
}
