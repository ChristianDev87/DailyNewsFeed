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

    // ── BuildDigestText ──────────────────────────────────────────────────────────

    [Fact]
    public void BuildDigestText_NoArticles_ContainsNoNewArticlesMessage()
    {
        var result = DigestService.BuildDigestText([], new DateTime(2026, 4, 9, 10, 0, 0));
        Assert.Contains("Keine neuen Artikel", result);
    }

    [Fact]
    public void BuildDigestText_FirstRunOfDay_UsesNewsDigestHeader()
    {
        // 02:00 Uhr liegt in IsFirstRunToday (0–4h)
        var berlinNow = new DateTime(2026, 4, 9, 2, 0, 0);
        var articles  = new List<ProcessedArticle>
        {
            new("Test Titel", "https://example.com/1", "Summary", "hash1", "TestFeed")
        };
        var categories = new List<(CategoryData, List<ProcessedArticle>)>
        {
            (new CategoryData("Technologie", "💻", []), articles)
        };

        var result = DigestService.BuildDigestText(categories, berlinNow);

        Assert.StartsWith("📰 **News-Digest", result);
    }

    [Fact]
    public void BuildDigestText_LaterRun_UsesUpdateHeader()
    {
        // 14:00 Uhr ist kein erster Lauf
        var berlinNow = new DateTime(2026, 4, 9, 14, 0, 0);
        var articles  = new List<ProcessedArticle>
        {
            new("Test Titel", "https://example.com/1", "Summary", "hash1", "TestFeed")
        };
        var categories = new List<(CategoryData, List<ProcessedArticle>)>
        {
            (new CategoryData("Technologie", "💻", []), articles)
        };

        var result = DigestService.BuildDigestText(categories, berlinNow);

        Assert.StartsWith("🔄 **Update", result);
    }

    [Fact]
    public void BuildDigestText_ArticleWithSummary_IncludesSummaryAndUrl()
    {
        var berlinNow = new DateTime(2026, 4, 9, 10, 0, 0);
        var articles  = new List<ProcessedArticle>
        {
            new("Test Titel", "https://example.com/1", "Das ist eine Zusammenfassung", "hash1", "TestFeed")
        };
        var categories = new List<(CategoryData, List<ProcessedArticle>)>
        {
            (new CategoryData("Technologie", "💻", []), articles)
        };

        var result = DigestService.BuildDigestText(categories, berlinNow);

        Assert.Contains("Das ist eine Zusammenfassung", result);
        Assert.Contains("<https://example.com/1>", result);
    }

    [Fact]
    public void BuildDigestText_ArticleWithoutSummary_NoTripleNewline()
    {
        var berlinNow = new DateTime(2026, 4, 9, 10, 0, 0);
        var articles  = new List<ProcessedArticle>
        {
            new("Test Titel", "https://example.com/1", "", "hash1", "TestFeed")
        };
        var categories = new List<(CategoryData, List<ProcessedArticle>)>
        {
            (new CategoryData("Tech", "💻", []), articles)
        };

        var result = DigestService.BuildDigestText(categories, berlinNow);

        Assert.DoesNotContain("\n\n\n", result);
    }

    // ── BuildHeaderText ───────────────────────────────────────────────────────

    [Theory]
    [InlineData(2,  "📰 **News-Digest")]   // first run of day (0–4h)
    [InlineData(14, "🔄 **Update")]        // later run
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
}
