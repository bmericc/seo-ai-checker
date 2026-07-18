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
}
