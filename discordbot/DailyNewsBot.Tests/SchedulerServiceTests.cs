using DailyNewsBot.Services;

namespace DailyNewsBot.Tests;

public class SchedulerServiceTests
{
    private static readonly TimeZoneInfo _berlin =
        TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin");

    /// <summary>Converts a naive Berlin local time to UTC.</summary>
    private static DateTime BerlinToUtc(int year, int month, int day, int hour, int minute)
    {
        var local = new DateTime(year, month, day, hour, minute, 0, DateTimeKind.Unspecified);
        return TimeZoneInfo.ConvertTimeToUtc(local, _berlin);
    }

    private static DateTime UtcToBerlin(DateTime utc)
        => TimeZoneInfo.ConvertTimeFromUtc(utc, _berlin);

    [Theory]
    // (year, month, day, inputHour, inputMinute, expectedHour, expectedMinute)
    [InlineData(2026, 1, 16,  0, 30,  4, 0)]  // 00:30 → 04:00
    [InlineData(2026, 1, 16,  3, 59,  4, 0)]  // 03:59 → 04:00
    [InlineData(2026, 1, 16,  4,  0,  8, 0)]  // 04:00 → 08:00
    [InlineData(2026, 1, 16,  7, 59,  8, 0)]  // 07:59 → 08:00
    [InlineData(2026, 1, 16, 12,  0, 16, 0)]  // 12:00 → 16:00
    [InlineData(2026, 1, 16, 20,  0,  0, 0)]  // 20:00 → 00:00 next day
    [InlineData(2026, 1, 16, 23, 30,  0, 0)]  // 23:30 → 00:00 next day
    [InlineData(2026, 1, 16, 23, 59,  0, 0)]  // 23:59 → 00:00 next day
    public void GetNextRunTime_ReturnsCorrectNextBerlinHour(
        int year, int month, int day,
        int inputHour, int inputMinute,
        int expectedHour, int expectedMinute)
    {
        var nowUtc  = BerlinToUtc(year, month, day, inputHour, inputMinute);
        var nextUtc = SchedulerService.GetNextRunTime(nowUtc);
        var next    = UtcToBerlin(nextUtc);

        Assert.Equal(expectedHour, next.Hour);
        Assert.Equal(expectedMinute, next.Minute);
        Assert.True(nextUtc > nowUtc, "Nächster Lauf muss in der Zukunft liegen");
    }

    [Fact]
    public void GetNextRunTime_SummerTime_IsCorrect()
    {
        // Juli: Berlin = UTC+2; 10:00 Berlin → nächster Block: 12:00 Berlin
        var nowUtc  = BerlinToUtc(2026, 7, 15, 10, 0);
        var nextUtc = SchedulerService.GetNextRunTime(nowUtc);
        var next    = UtcToBerlin(nextUtc);

        Assert.Equal(12, next.Hour);
        Assert.True(nextUtc > nowUtc);
    }

    [Fact]
    public void GetNextRunTime_ResultIsAlwaysUtcKind()
    {
        // Rückgabewert muss DateTimeKind.Utc sein — sonst schlägt Task.Delay fehl
        var nowUtc = BerlinToUtc(2026, 3, 15, 9, 0);
        var result = SchedulerService.GetNextRunTime(nowUtc);

        Assert.Equal(DateTimeKind.Utc, result.Kind);
    }

    [Theory]
    [InlineData(2026, 1, 16,  0, 30)]
    [InlineData(2026, 1, 16,  7, 59)]
    [InlineData(2026, 1, 16, 13, 15)]
    [InlineData(2026, 1, 16, 23, 59)]
    [InlineData(2026, 7, 15, 18,  0)]  // Sommerzeit
    public void GetNextRunTime_ResultHourIsAlways4HourBlock(
        int year, int month, int day, int hour, int minute)
    {
        // Ergebnis-Stunde (Berlin) muss immer 0, 4, 8, 12, 16 oder 20 sein
        var nowUtc  = BerlinToUtc(year, month, day, hour, minute);
        var nextUtc = SchedulerService.GetNextRunTime(nowUtc);
        var next    = UtcToBerlin(nextUtc);

        Assert.Contains(next.Hour, new[] { 0, 4, 8, 12, 16, 20 });
    }
}
