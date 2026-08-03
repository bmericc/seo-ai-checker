<?php

namespace Tests\Unit;

use App\Services\OnPage\OnPageSeoAnalyzer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class OnPageSeoAnalyzerTest extends TestCase
{
    private function analyzerWithHtml(string $html): OnPageSeoAnalyzer
    {
        $mock = new MockHandler([new Response(200, [], $html)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new OnPageSeoAnalyzer($client);
    }

    public function test_og_and_twitter_tags_are_extracted(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <meta property="og:title" content="Ornek Baslik">
                <meta property="og:image" content="https://example.com/img.png">
                <meta name="twitter:card" content="summary_large_image">
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertSame('Ornek Baslik', $result->ogTags['og:title']);
        $this->assertSame('https://example.com/img.png', $result->ogTags['og:image']);
        $this->assertNull($result->ogTags['og:description']);
        $this->assertSame('summary_large_image', $result->twitterCard);
    }

    public function test_schema_types_are_collected_from_json_ld_including_graph_and_arrays(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <script type="application/ld+json">{"@type": "Article"}</script>
                <script type="application/ld+json">{"@graph": [{"@type": "Organization"}, {"@type": ["Product", "WPHeader"]}]}</script>
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->hasStructuredData);
        $types = $result->schemaTypes;
        sort($types);
        $this->assertSame(['Article', 'Organization', 'Product', 'WPHeader'], $types);
        $this->assertSame(['WPHeader'], $result->deprecatedSchemaTypes);
    }

    public function test_no_json_ld_means_no_structured_data(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertFalse($result->hasStructuredData);
        $this->assertSame([], $result->schemaTypes);
    }

    public function test_canonical_pointing_to_the_same_page_is_self(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head><link rel="canonical" href="https://example.com/page/"></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertSame('self', $result->canonicalStatus);
    }

    public function test_canonical_pointing_elsewhere_is_different(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head><link rel="canonical" href="https://example.com/other-page"></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertSame('different', $result->canonicalStatus);
    }

    public function test_missing_canonical_is_reported(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertSame('missing', $result->canonicalStatus);
    }

    public function test_heading_level_skip_is_detected(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><h1>T</h1><h2>A</h2><h4>Skipped</h4></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->headingHierarchySkip);
    }

    public function test_normal_heading_order_has_no_skip(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><h1>T</h1><h2>A</h2><h3>B</h3><h2>C</h2></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertFalse($result->headingHierarchySkip);
    }

    public function test_h1_count_reflects_number_of_h1_tags(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><h1>One</h1><h1>Two</h1></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertSame(2, $result->h1Count());
    }

    public function test_valid_hreflang_set_with_self_reference_and_x_default_has_no_issues(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <link rel="alternate" hreflang="en" href="https://example.com/page">
                <link rel="alternate" hreflang="tr-TR" href="https://example.com/tr/page">
                <link rel="alternate" hreflang="x-default" href="https://example.com/page">
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertSame('https://example.com/page', $result->hreflangTags['en']);
        $this->assertSame([], $result->hreflangIssues);
    }

    public function test_invalid_hreflang_code_is_flagged(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head><link rel="alternate" hreflang="turkish" href="https://example.com/tr"></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertTrue(collect($result->hreflangIssues)->contains(fn ($i) => str_contains($i, 'turkish')));
    }

    public function test_duplicate_hreflang_code_is_flagged(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <link rel="alternate" hreflang="en" href="https://example.com/page">
                <link rel="alternate" hreflang="en" href="https://example.com/en-gb/page">
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertTrue(collect($result->hreflangIssues)->contains(fn ($i) => str_contains($i, 'Yinelenen')));
    }

    public function test_missing_self_reference_in_hreflang_set_is_flagged(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head><link rel="alternate" hreflang="tr" href="https://example.com/tr/page"></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertTrue(collect($result->hreflangIssues)->contains(fn ($i) => str_contains($i, 'self-reference')));
    }

    public function test_no_hreflang_tags_means_no_issues(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/page');

        $this->assertSame([], $result->hreflangTags);
        $this->assertSame([], $result->hreflangIssues);
    }

    public function test_image_stats_are_collected(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><body>
                <img src="/a.webp" alt="ok" width="100" height="100" loading="lazy">
                <img src="/b.jpg" width="50" height="50">
                <img src="/c.png">
            </body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertSame(3, $result->imageStats['total']);
        $this->assertSame(2, $result->imageStats['missing_alt']);
        $this->assertSame(1, $result->imageStats['missing_dimensions']);
        $this->assertSame(2, $result->imageStats['not_lazy']);
        $this->assertSame(2, $result->imageStats['legacy_format']);
    }

    public function test_author_is_detected_from_meta_tag(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><head><meta name="author" content="Ayşe Yılmaz"></head><body></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->authorDetected);
        $this->assertSame('Ayşe Yılmaz', $result->authorName);
    }

    public function test_author_is_detected_from_json_ld(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <script type="application/ld+json">{"@type": "Article", "author": {"name": "Ayşe Yılmaz"}}</script>
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->authorDetected);
        $this->assertSame('Ayşe Yılmaz', $result->authorName);
    }

    public function test_author_is_detected_from_byline_class(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><span class="byline">Ayşe Yılmaz</span></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->authorDetected);
    }

    public function test_no_author_signal_means_not_detected(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><p>Merhaba dünya</p></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertFalse($result->authorDetected);
        $this->assertNull($result->authorName);
    }

    public function test_published_date_is_detected_from_json_ld(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <script type="application/ld+json">{"@type": "Article", "datePublished": "2026-01-15"}</script>
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->publishedDateDetected);
        $this->assertSame('2026-01-15', $result->publishedDate);
    }

    public function test_published_date_is_detected_from_time_tag(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><time datetime="2026-02-01">1 Şubat</time></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->publishedDateDetected);
        $this->assertSame('2026-02-01', $result->publishedDate);
    }

    public function test_about_and_contact_links_are_detected(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><body>
                <a href="/hakkimizda">Hakkımızda</a>
                <a href="/iletisim">İletişim</a>
            </body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue($result->aboutPageLinked);
        $this->assertTrue($result->contactPageLinked);
    }

    public function test_no_about_or_contact_link_means_not_detected(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body><a href="/blog">Blog</a></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertFalse($result->aboutPageLinked);
        $this->assertFalse($result->contactPageLinked);
    }

    public function test_faq_schema_is_recommended_when_question_headings_exist_and_faqpage_is_missing(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><body>
                <h2>Bu ürün nasıl çalışır?</h2>
                <h2>Kargo ne zaman gelir?</h2>
            </body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue(collect($result->recommendedSchemaTypes)->contains(fn ($r) => $r['type'] === 'FAQPage'));
    }

    public function test_faq_schema_is_not_recommended_when_already_present(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <script type="application/ld+json">{"@type": "FAQPage"}</script>
            </head><body>
                <h2>Bu ürün nasıl çalışır?</h2>
                <h2>Kargo ne zaman gelir?</h2>
            </body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertFalse(collect($result->recommendedSchemaTypes)->contains(fn ($r) => $r['type'] === 'FAQPage'));
    }

    public function test_howto_schema_is_recommended_for_step_by_step_content(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><body>
                <h1>Nasıl yapılır: Adım adım rehber</h1>
                <ol><li>Birinci adım</li><li>İkinci adım</li><li>Üçüncü adım</li></ol>
            </body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue(collect($result->recommendedSchemaTypes)->contains(fn ($r) => $r['type'] === 'HowTo'));
    }

    public function test_article_schema_is_recommended_when_author_and_date_are_present(): void
    {
        $analyzer = $this->analyzerWithHtml(<<<'HTML'
            <html><head>
                <meta name="author" content="Ayşe Yılmaz">
                <script type="application/ld+json">{"datePublished": "2026-01-15"}</script>
            </head><body></body></html>
            HTML);

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue(collect($result->recommendedSchemaTypes)->contains(fn ($r) => $r['type'] === 'Article'));
    }

    public function test_organization_schema_is_recommended_on_the_root_page_when_missing(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body></body></html>');

        $result = $analyzer->analyze('https://example.com/');

        $this->assertTrue(collect($result->recommendedSchemaTypes)->contains(fn ($r) => $r['type'] === 'Organization'));
    }

    public function test_organization_schema_is_not_recommended_on_a_non_root_page(): void
    {
        $analyzer = $this->analyzerWithHtml('<html><body></body></html>');

        $result = $analyzer->analyze('https://example.com/blog/post');

        $this->assertFalse(collect($result->recommendedSchemaTypes)->contains(fn ($r) => $r['type'] === 'Organization'));
    }
}
