using System.Data.Common;

namespace DailyNewsBot.Data;

public interface IDatabase
{
    Task<DbConnection> GetOpenConnectionAsync(CancellationToken ct = default);
}
