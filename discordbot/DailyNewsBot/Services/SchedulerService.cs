using Dapper;
using DailyNewsBot.Data;

namespace DailyNewsBot.Services;

public class SchedulerService : BackgroundService
{
    private readonly DigestService _digestService;
    private readonly IBotClientProvider _clientProvider;
    private readonly IDatabase _db;
    private readonly ILogger<SchedulerService> _logger;

    public DateTime NextRunTime { get; private set; }

    public SchedulerService(
        DigestService digestService,
        IBotClientProvider clientProvider,
        IDatabase db,
        ILogger<SchedulerService> logger)
    {
        _digestService = digestService;
        _clientProvider = clientProvider;
        _db = db;
        _logger = logger;
        NextRunTime = GetNextRunTime();
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("SchedulerService gestartet. Nächster Lauf: {NextRun}",
            TimeZoneInfo.ConvertTimeFromUtc(NextRunTime, _tz).ToString("dd.MM.yyyy HH:mm") + " Uhr (Berlin)");

        while (!stoppingToken.IsCancellationRequested)
        {
            var delay = NextRunTime - DateTime.UtcNow;
            if (delay > TimeSpan.Zero)
                await Task.Delay(delay, stoppingToken);

            if (stoppingToken.IsCancellationRequested) break;

            await TryRunDigestAsync(stoppingToken);

            NextRunTime = GetNextRunTime();
            _logger.LogInformation("Nächster Scheduler-Lauf: {NextRun}",
                TimeZoneInfo.ConvertTimeFromUtc(NextRunTime, _tz).ToString("dd.MM.yyyy HH:mm") + " Uhr (Berlin)");
        }
    }

    private async Task TryRunDigestAsync(CancellationToken ct)
    {
        await using var conn = await _db.GetOpenConnectionAsync(ct);

        var lockResult = await conn.ExecuteScalarAsync<int>(
            "SELECT GET_LOCK('daily_news_scheduler', 0)");

        if (lockResult != 1)
        {
            _logger.LogInformation("Scheduler-Lock nicht erhalten — andere Instanz aktiv");
            return;
        }

        var cmdId = await conn.ExecuteScalarAsync<long>(
            "INSERT INTO bot_commands (command, status, created_by, created_at) " +
            "VALUES ('run_digest', 'pending', 'scheduler', NOW()); SELECT LAST_INSERT_ID()");

        var status = "done";
        try
        {
            _logger.LogInformation("Scheduler-Lock erhalten — starte Digest-Lauf");
            await _digestService.RunAllChannelsAsync(_clientProvider, ct);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Fehler im Scheduler-Digest-Lauf");
            status = "failed";
        }
        finally
        {
            await conn.ExecuteAsync(
                "UPDATE bot_commands SET status = @status, executed_at = NOW() WHERE id = @cmdId",
                new { status, cmdId });
            await conn.ExecuteScalarAsync<int>("SELECT RELEASE_LOCK('daily_news_scheduler')");
            _logger.LogInformation("Scheduler-Lock freigegeben");
        }
    }

    private static readonly TimeZoneInfo _tz =
        TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin");

    internal static DateTime GetNextRunTime(DateTime? nowUtc = null)
    {
        var utcNow   = nowUtc ?? DateTime.UtcNow;
        var nowLocal = TimeZoneInfo.ConvertTimeFromUtc(utcNow, _tz);

        var currentBlock = nowLocal.Hour - (nowLocal.Hour % 4);
        var nextLocal    = nowLocal.Date.AddHours(currentBlock + 4);
        if (nextLocal <= nowLocal)
            nextLocal = nextLocal.AddHours(4);

        return TimeZoneInfo.ConvertTimeToUtc(nextLocal, _tz);
    }
}
