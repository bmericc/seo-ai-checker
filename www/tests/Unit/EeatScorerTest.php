<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DomainFact;
use App\Services\Eeat\EeatScorer;
use App\Services\OnPage\OnPageSeoResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EeatScorerTest extends TestCase
{
    use RefreshDatabase;

    private function onPage(array $overrides = []): OnPageSeoResult
    {
        return new OnPageSeoResult(
            url: $overrides['url'] ?? 'https://example.com/page',
            title: 'Baslik',
            metaDescription: 'Aciklama',
            canonical: 'https://example.com/page',
            metaRobots: 'index,follow',
            h1s: ['Baslik'],
            h2Count: 2,
            wordCount: $overrides['wordCount'] ?? 500,
            keyword: 'ornek anahtar kelime',
            keywordDensityPercent: 1.0,
            keywordInTitle: true,
            keywordInH1: true,
            keywordInDescription: true,
            imagesMissingAlt: 0,
            internalLinks: 5,
            externalLinks: 2,
            hasStructuredData: $overrides['hasStructuredData'] ?? false,
            fetchTimeMs: 120.0,
            authorDetected: $overrides['authorDetected'] ?? false,
            publishedDateDetected: $overrides['publishedDateDetected'] ?? false,
            aboutPageLinked: $overrides['aboutPageLinked'] ?? false,
            contactPageLinked: $overrides['contactPageLinked'] ?? false,
        );
    }

    private function fact(array $attributes = []): DomainFact
    {
        return DomainFact::query()->create(array_merge(['domain' => 'example.com'], $attributes));
    }

    public function test_all_signals_absent_scores_zero(): void
    {
        $result = (new EeatScorer())->score($this->onPage(['wordCount' => 0]), null, null);

        $this->assertSame(0, $result->score);
        $this->assertSame(0, $result->expertiseScore);
        $this->assertSame(0, $result->authorityScore);
        $this->assertSame(0, $result->trustScore);
        $this->assertSame(0, $result->experienceScore);
    }

    public function test_author_and_published_date_give_full_expertise_score(): void
    {
        $onPage = $this->onPage(['authorDetected' => true, 'publishedDateDetected' => true]);

        $result = (new EeatScorer())->score($onPage, null, null);

        $this->assertSame(25, $result->expertiseScore);
        $this->assertTrue($result->signals['author']['present']);
        $this->assertSame(15, $result->signals['author']['points']);
        $this->assertTrue($result->signals['published_date']['present']);
        $this->assertSame(10, $result->signals['published_date']['points']);
    }

    #[DataProvider('domainAgeProvider')]
    public function test_domain_age_tiers_award_the_expected_points(?int $years, int $expectedPoints): void
    {
        $fact = $years === null
            ? $this->fact()
            : $this->fact(['whois_registered_at' => now()->subYears($years)]);

        $result = (new EeatScorer())->score($this->onPage(), $fact, null);

        $this->assertSame($expectedPoints, $result->signals['domain_age']['points']);
    }

    public static function domainAgeProvider(): array
    {
        return [
            'no whois data' => [null, 0],
            'under a year' => [0, 0],
            'one year' => [1, 5],
            'two years' => [2, 10],
            'four years (still tier 2+)' => [4, 10],
            'five years or more' => [5, 15],
            'ten years' => [10, 15],
        ];
    }

    public function test_backlinks_signal_requires_verified_and_positive_total_links(): void
    {
        $scorer = new EeatScorer();

        $absent = $scorer->score($this->onPage(), null, null);
        $this->assertFalse($absent->signals['backlinks']['present']);

        $notVerified = $scorer->score($this->onPage(), null, ['verified' => false, 'total_links' => 10]);
        $this->assertFalse($notVerified->signals['backlinks']['present']);

        $zeroLinks = $scorer->score($this->onPage(), null, ['verified' => true, 'total_links' => 0]);
        $this->assertFalse($zeroLinks->signals['backlinks']['present']);

        $present = $scorer->score($this->onPage(), null, ['verified' => true, 'total_links' => 10]);
        $this->assertTrue($present->signals['backlinks']['present']);
        $this->assertSame(10, $present->signals['backlinks']['points']);
    }

    public function test_trust_score_combines_https_headers_and_about_contact(): void
    {
        $fact = $this->fact([
            'security_headers' => [
                'http_redirects_to_https' => true,
                'headers' => ['x-frame-options' => true, 'x-content-type-options' => true, 'strict-transport-security' => false],
            ],
        ]);
        $onPage = $this->onPage(['aboutPageLinked' => true]);

        $result = (new EeatScorer())->score($onPage, $fact, null);

        $this->assertSame(30, $result->trustScore);
        $this->assertTrue($result->signals['https']['present']);
        $this->assertTrue($result->signals['security_headers']['present']);
        $this->assertTrue($result->signals['about_contact']['present']);
    }

    public function test_security_headers_signal_is_absent_with_fewer_than_two_present_headers(): void
    {
        $fact = $this->fact([
            'security_headers' => [
                'headers' => ['x-frame-options' => true, 'x-content-type-options' => false],
            ],
        ]);

        $result = (new EeatScorer())->score($this->onPage(), $fact, null);

        $this->assertFalse($result->signals['security_headers']['present']);
        $this->assertSame(0, $result->signals['security_headers']['points']);
    }

    public function test_experience_score_combines_content_depth_and_structured_data(): void
    {
        $onPage = $this->onPage(['wordCount' => 300, 'hasStructuredData' => true]);

        $result = (new EeatScorer())->score($onPage, null, null);

        $this->assertSame(20, $result->experienceScore);
    }

    public function test_content_depth_signal_is_absent_below_the_word_count_threshold(): void
    {
        $onPage = $this->onPage(['wordCount' => 299]);

        $result = (new EeatScorer())->score($onPage, null, null);

        $this->assertFalse($result->signals['content_depth']['present']);
    }

    public function test_overall_score_sums_all_four_category_scores(): void
    {
        $fact = $this->fact([
            'whois_registered_at' => now()->subYears(6),
            'security_headers' => [
                'http_redirects_to_https' => true,
                'headers' => ['x-frame-options' => true, 'x-content-type-options' => true],
            ],
        ]);
        $onPage = $this->onPage([
            'authorDetected' => true,
            'publishedDateDetected' => true,
            'aboutPageLinked' => true,
            'wordCount' => 500,
            'hasStructuredData' => true,
        ]);
        $bingBacklinks = ['verified' => true, 'total_links' => 42];

        $result = (new EeatScorer())->score($onPage, $fact, $bingBacklinks);

        $this->assertSame(25, $result->expertiseScore);
        $this->assertSame(25, $result->authorityScore);
        $this->assertSame(30, $result->trustScore);
        $this->assertSame(20, $result->experienceScore);
        $this->assertSame(100, $result->score);
    }
}
