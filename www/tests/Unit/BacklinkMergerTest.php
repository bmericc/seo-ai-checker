<?php

namespace Tests\Unit;

use App\Support\BacklinkMerger;
use PHPUnit\Framework\TestCase;

class BacklinkMergerTest extends TestCase
{
    private function dfs(string $domain, int $rank, int $backlinks = 1, bool $nofollow = false, ?string $firstSeen = '2026-01-01'): array
    {
        return ['domain' => $domain, 'rank' => $rank, 'backlinks' => $backlinks, 'nofollow' => $nofollow, 'spam_score' => 10, 'first_seen' => $firstSeen, 'lost_date' => null];
    }

    private function cg(string $domain, int $authority, int $hosts = 1): array
    {
        return ['domain' => $domain, 'hosts' => $hosts, 'tld' => 'com', 'authority' => $authority];
    }

    public function test_same_domain_from_both_sources_becomes_one_row(): void
    {
        $rows = BacklinkMerger::merge(
            [$this->dfs('www.ornek.com.tr', 300, 4)],
            [$this->cg('ornek.com.tr', 25, 3)],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('ornek.com.tr', $rows[0]['domain']);
        $this->assertSame([BacklinkMerger::SOURCE_CRAWLGRAPH, BacklinkMerger::SOURCE_DATAFORSEO], $rows[0]['sources']);
        $this->assertSame(300, $rows[0]['rank']);
        $this->assertSame(4, $rows[0]['backlinks']);
        $this->assertSame(25, $rows[0]['authority']);
        $this->assertSame(3, $rows[0]['hosts']);
    }

    public function test_subdomains_fold_into_crawlgraph_registrable_domain(): void
    {
        $rows = BacklinkMerger::merge(
            [
                $this->dfs('blog.ornek.com', 200, 2, nofollow: true, firstSeen: '2025-05-01'),
                $this->dfs('shop.ornek.com', 250, 3, nofollow: false, firstSeen: '2024-02-01'),
            ],
            [$this->cg('ornek.com', 10, 2)],
        );

        $this->assertCount(1, $rows);
        $this->assertSame(250, $rows[0]['rank']);
        $this->assertSame(5, $rows[0]['backlinks']);
        $this->assertFalse($rows[0]['nofollow']);
        $this->assertSame('2024-02-01', $rows[0]['first_seen']);
    }

    public function test_unmatched_rows_stay_separate_and_dataforseo_rows_sort_first(): void
    {
        $rows = BacklinkMerger::merge(
            [$this->dfs('low.com', 5), $this->dfs('high.com', 500)],
            [$this->cg('only-cc.com', 90), $this->cg('weak-cc.com', 3)],
        );

        $this->assertSame(['high.com', 'low.com', 'only-cc.com', 'weak-cc.com'], array_column($rows, 'domain'));
        $this->assertSame([BacklinkMerger::SOURCE_CRAWLGRAPH], $rows[2]['sources']);
        $this->assertNull($rows[2]['rank']);
    }

    public function test_suffix_match_does_not_cross_label_boundaries(): void
    {
        $rows = BacklinkMerger::merge([$this->dfs('notornek.com', 100)], [$this->cg('ornek.com', 10)]);

        $this->assertSame(['notornek.com', 'ornek.com'], array_column($rows, 'domain'));
    }

    public function test_null_lists_are_treated_as_empty(): void
    {
        $this->assertSame([], BacklinkMerger::merge(null, null));
        $this->assertCount(1, BacklinkMerger::merge(null, [$this->cg('a.com', 1)]));
    }

    public function test_bing_rows_fold_into_existing_domains_and_add_bing_only_rows(): void
    {
        $rows = BacklinkMerger::merge(
            [$this->dfs('www.shared.com', 300, 4)],
            [$this->cg('shared.com', 25)],
            [
                ['domain' => 'blog.shared.com', 'links' => 3, 'anchor' => 'marka', 'url' => 'https://blog.shared.com/a', 'target' => 'https://ex.com/'],
                ['domain' => 'bing-only.org', 'links' => 2, 'anchor' => null, 'url' => 'https://bing-only.org/p', 'target' => 'https://ex.com/'],
            ],
        );

        $this->assertSame(['shared.com', 'bing-only.org'], array_column($rows, 'domain'));
        $this->assertSame([BacklinkMerger::SOURCE_CRAWLGRAPH, BacklinkMerger::SOURCE_DATAFORSEO, BacklinkMerger::SOURCE_BING], $rows[0]['sources']);
        $this->assertSame(3, $rows[0]['bing_links']);
        $this->assertSame('marka', $rows[0]['anchor']);
        $this->assertSame(4, $rows[0]['backlinks']);
        $this->assertSame([BacklinkMerger::SOURCE_BING], $rows[1]['sources']);
        $this->assertNull($rows[1]['backlinks']);
        $this->assertSame('https://bing-only.org/p', $rows[1]['sample_url']);
    }
}
