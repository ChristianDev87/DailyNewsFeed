using System.Data.Common;
using MySqlConnector;

namespace DailyNewsBot.Data;

public class Database : IDatabase
{
    private readonly string _connectionString;

    public Database(IConfiguration config)
    {
        var host    = config["DB_HOST"]          ?? "localhost";
        var port    = config["DB_PORT"]          ?? "3306";
        var name    = config["DB_NAME"]          ?? "daily_news";
        var user    = config["DB_USER"]          ?? throw new InvalidOperationException("DB_USER nicht gesetzt");
        var pass    = config["DB_PASS"]          ?? throw new InvalidOperationException("DB_PASS nicht gesetzt");
        var pool    = config["DB_MAX_POOL_SIZE"] ?? "20";

        _connectionString =
            $"Server={host};Port={port};Database={name};" +
            $"User={user};Password={pass};" +
            $"MaximumPoolSize={pool};AllowZeroDateTime=True;ConvertZeroDateTime=True;";
    }

    public MySqlConnection GetConnection() => new(_connectionString);

    // Opens a connection and immediately runs SET time_zone = '+00:00' so that
    // NOW() always returns UTC regardless of the server's system timezone.
    public async Task<DbConnection> GetOpenConnectionAsync(CancellationToken ct = default)
    {
        var conn = GetConnection();
        await conn.OpenAsync(ct);
        await using var cmd = conn.CreateCommand();
        cmd.CommandText = "SET time_zone = '+00:00'";
        await cmd.ExecuteNonQueryAsync(ct);
        return conn;
    }
}
