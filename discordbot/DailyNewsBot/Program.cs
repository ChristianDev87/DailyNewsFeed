using Dapper;
using DailyNewsBot.Data;
using DailyNewsBot.Processing;
using DailyNewsBot.Services;
using DotNetEnv;
using Serilog;
using Serilog.Formatting.Compact;

// .env suchen: vom aktuellen Verzeichnis aufwärts bis zur Wurzel
var searchDir = new DirectoryInfo(Directory.GetCurrentDirectory());
string? envPath = null;
while (searchDir != null)
{
    var candidate = Path.Combine(searchDir.FullName, ".env");
    if (File.Exists(candidate)) { envPath = candidate; break; }
    searchDir = searchDir.Parent;
}
if (envPath != null)
    Env.Load(envPath);

const string consoleTemplate =
    "{Timestamp:HH:mm:ss} [{Level:u3}] {SourceContext:l}: {Message:lj}{NewLine}{Exception}";

Log.Logger = new LoggerConfiguration()
    .MinimumLevel.Information()
    .MinimumLevel.Override("Microsoft", Serilog.Events.LogEventLevel.Warning)
    .MinimumLevel.Override("Discord", Serilog.Events.LogEventLevel.Warning)
    .WriteTo.Console(outputTemplate: consoleTemplate)
    .WriteTo.File(new CompactJsonFormatter(), "logs/bot-.log",
        rollingInterval: RollingInterval.Day,
        retainedFileCountLimit: 14)
    .CreateBootstrapLogger();

try
{
    Log.Information("Daily News Bot startet...");

    var builder = WebApplication.CreateBuilder(args);
    builder.WebHost.UseUrls("http://+:8080");

    builder.Host.UseSerilog((ctx, services, cfg) => cfg
        .ReadFrom.Configuration(ctx.Configuration)
        .MinimumLevel.Override("Discord", Serilog.Events.LogEventLevel.Warning)
        .WriteTo.Console(outputTemplate: consoleTemplate)
        .WriteTo.File(new CompactJsonFormatter(), "logs/bot-.log",
            rollingInterval: RollingInterval.Day,
            retainedFileCountLimit: 14));

    builder.Services.AddSingleton<Database>();
    builder.Services.AddHttpClient("feeds", client =>
    {
        client.Timeout = TimeSpan.FromSeconds(15);
        client.DefaultRequestHeaders.Add(
            "User-Agent",
            "DailyNewsBot/1.0 (+https://github.com/ChristianDev87/DailyNewsFeed-Bot; RSS reader)");
        client.DefaultRequestHeaders.Add(
            "Accept",
            "application/rss+xml, application/atom+xml, application/xml;q=0.9, text/xml;q=0.8, */*;q=0.5");
        client.DefaultRequestHeaders.Add(
            "Accept-Language",
            "de, en;q=0.9");
    }).ConfigurePrimaryHttpMessageHandler(() => new HttpClientHandler
    {
        AutomaticDecompression = System.Net.DecompressionMethods.GZip | System.Net.DecompressionMethods.Deflate,
    });
    builder.Services.AddSingleton<FeedFetcher>();
    builder.Services.AddSingleton<DigestService>();

    builder.Services.AddSingleton<BotService>();
    builder.Services.AddHostedService(sp => sp.GetRequiredService<BotService>());
    builder.Services.AddSingleton<IBotClientProvider>(sp => sp.GetRequiredService<BotService>());
    builder.Services.AddHostedService<SchedulerService>();
    builder.Services.AddHostedService<HeartbeatService>();

    var app = builder.Build();

    app.MapPost("/internal/run-digest", (
        DigestService digestService,
        IBotClientProvider clientProvider,
        Database db,
        IHostApplicationLifetime lifetime,
        long cmdId = 0) =>
    {
        _ = Task.Run(async () =>
        {
            var status = "done";
            try
            {
                await digestService.RunAllChannelsAsync(clientProvider, lifetime.ApplicationStopping);
            }
            catch (Exception ex)
            {
                Log.Error(ex, "Fehler im Digest-Hintergrundlauf (cmdId={CmdId})", cmdId);
                status = "failed";
            }
            finally
            {
                if (cmdId > 0)
                {
                    try
                    {
                        using var conn = await db.GetOpenConnectionAsync();
                        await conn.ExecuteAsync(
                            "UPDATE bot_commands SET status = @status, executed_at = NOW() WHERE id = @cmdId",
                            new { status, cmdId });
                    }
                    catch (Exception ex)
                    {
                        Log.Error(ex, "Fehler beim Aktualisieren von bot_commands (id={CmdId})", cmdId);
                    }
                }
            }
        });

        return Results.Accepted();
    });

    await app.RunAsync();
}
catch (Exception ex)
{
    Log.Fatal(ex, "Bot konnte nicht gestartet werden");
    return 1;
}
finally
{
    await Log.CloseAndFlushAsync();
}

return 0;
