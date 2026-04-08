using DailyNewsBot.Services;

namespace DailyNewsBot.Tests;

public class DigestServiceTests
{
    [Theory]
    [InlineData(0,  true)]   // Mitternacht — erster Lauf
    [InlineData(1,  true)]   // 01:00 — noch erster Lauf
    [InlineData(3,  true)]   // 03:00 — noch erster Lauf
    [InlineData(4,  false)]  // 04:00 — zweiter Lauf des Tages
    [InlineData(12, false)]  // Mittag
    [InlineData(20, false)]  // Abend
    [InlineData(23, false)]  // Kurz vor Mitternacht
    public void IsFirstRunToday_ReturnsTrue_OnlyInFirstFourHours(int hour, bool expected)
    {
        var berlinNow = new DateTime(2026, 4, 8, hour, 30, 0);
        Assert.Equal(expected, DigestService.IsFirstRunToday(berlinNow));
    }
}
