using DailyNewsBot.Models;
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

    // ── BuildHeaderText ───────────────────────────────────────────────────────

    [Theory]
    [InlineData(0,  "📰 **News-Digest")]   // Mitternacht — erster Lauf
    [InlineData(2,  "📰 **News-Digest")]   // mitten im ersten Lauf
    [InlineData(3,  "📰 **News-Digest")]   // letzter erster Lauf
    [InlineData(4,  "🔄 **Update")]        // erster späterer Lauf
    [InlineData(14, "🔄 **Update")]        // Mittag
    public void BuildHeaderText_ReturnsCorrectHeaderForHour(int hour, string expectedStart)
    {
        var result = DigestService.BuildHeaderText(new DateTime(2026, 4, 9, hour, 0, 0));
        Assert.StartsWith(expectedStart, result);
    }

    // ── BuildCategoryText ─────────────────────────────────────────────────────

    [Fact]
    public void BuildCategoryText_ContainsEmojiLabelAndArticle()
    {
        var cat = new CategoryData("Technologie", "💻", []);
        var articles = new List<ProcessedArticle>
        {
            new("Test Titel", "https://example.com/1", "Summary", "hash1", "TestFeed")
        };
        var result = DigestService.BuildCategoryText(cat, articles);
        Assert.Contains("💻 Technologie", result);
        Assert.Contains("🔹 **Test Titel**", result);
        Assert.Contains("<https://example.com/1>", result);
        Assert.Contains("Summary", result);
    }

    [Fact]
    public void BuildCategoryText_ArticleWithoutSummary_NoTripleNewline()
    {
        var cat = new CategoryData("Tech", "💻", []);
        var articles = new List<ProcessedArticle>
        {
            new("Test Titel", "https://example.com/1", "", "hash1", "TestFeed")
        };
        var result = DigestService.BuildCategoryText(cat, articles);
        Assert.DoesNotContain("\n\n\n", result);
    }

    // In der Praxis wird BuildCategoryText nur mit nicht-leeren Listen aufgerufen
    // (gefiltert durch allNew in RunSingleChannelAsync). Test dokumentiert das Verhalten der Methode selbst.
    [Fact]
    public void BuildCategoryText_EmptyArticleList_ContainsOnlyHeader()
    {
        var cat = new CategoryData("Tech", "💻", []);
        var result = DigestService.BuildCategoryText(cat, []);
        Assert.Contains("💻 Tech", result);
        Assert.DoesNotContain("🔹", result);
    }
}
