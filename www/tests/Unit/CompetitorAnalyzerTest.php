<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Check;
use App\Models\Keyword;
use App\Services\Analytics\CompetitorAnalyzer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CompetitorAnalyzerTest extends TestCase
{
    /**
     * @param  list<array{position: int, domain: string}>  $organicResults
     */
    private function keyword(string $text, ?array $organicResults, bool $blocked = false): Keyword
    {
        $keyword = new Keyword(['keyword' => $text]);

        $check = null;
        if ($organicResults !== null) {
            $check = new Check();
            $check->setRawAttributes([
                'blocked' => $blocked,
                'organic_results' => json_encode(array_map(
                    static fn (array $r) => ['position' => $r['position'], 'url' => "https://{$r['domain']}/", 'domain' => $r['domain'], 'title' => ''],
                    $organicResults,
                )),
            ]);
        }
        $keyword->setRelation('latestCheck', $check);

        return $keyword;
    }

    public function test_ranks_competitors_by_number_of_distinct_shared_keywords(): void
    {
        $keywords = new Collection([
            $this->keyword('a', [['position' => 1, 'domain' => 'rival.com'], ['position' => 2, 'domain' => 'other.com']]),
            $this->keyword('b', [['position' => 3, 'domain' => 'rival.com']]),
            $this->keyword('c', [['position' => 5, 'domain' => 'other.com']]),
        ]);

        $result = (new CompetitorAnalyzer())->frequentCompetitors('example.com', $keywords);

        $this->assertSame(3, $result['tracked_keyword_count']);
        $this->assertSame('rival.com', $result['competitors'][0]['domain']);
        $this->assertSame(2, $result['competitors'][0]['keyword_count']);
        $this->assertSame(['a', 'b'], $result['competitors'][0]['keywords']);
    }

    public function test_own_domain_is_excluded_even_with_a_www_prefix(): void
    {
        $keywords = new Collection([
            $this->keyword('a', [['position' => 1, 'domain' => 'www.example.com'], ['position' => 2, 'domain' => 'rival.com']]),
        ]);

        $result = (new CompetitorAnalyzer())->frequentCompetitors('example.com', $keywords);

        $domains = array_column($result['competitors'], 'domain');
        $this->assertNotContains('www.example.com', $domains);
        $this->assertContains('rival.com', $domains);
    }

    public function test_average_and_best_position_are_computed_across_appearances(): void
    {
        $keywords = new Collection([
            $this->keyword('a', [['position' => 2, 'domain' => 'rival.com']]),
            $this->keyword('b', [['position' => 8, 'domain' => 'rival.com']]),
        ]);

        $result = (new CompetitorAnalyzer())->frequentCompetitors('example.com', $keywords);

        $this->assertSame(5.0, $result['competitors'][0]['average_position']);
        $this->assertSame(2, $result['competitors'][0]['best_position']);
    }

    public function test_blocked_checks_and_keywords_without_any_check_are_ignored(): void
    {
        $keywords = new Collection([
            $this->keyword('a', [['position' => 1, 'domain' => 'rival.com']], blocked: true),
            $this->keyword('b', null),
        ]);

        $result = (new CompetitorAnalyzer())->frequentCompetitors('example.com', $keywords);

        $this->assertSame(0, $result['tracked_keyword_count']);
        $this->assertSame([], $result['competitors']);
    }

    public function test_empty_keyword_collection_returns_no_competitors(): void
    {
        $result = (new CompetitorAnalyzer())->frequentCompetitors('example.com', new Collection());

        $this->assertSame(0, $result['tracked_keyword_count']);
        $this->assertSame([], $result['competitors']);
    }
}
