<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Check;
use App\Models\DomainCheck;
use App\Services\Analytics\ScoreHistoryBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ScoreHistoryBuilderTest extends TestCase
{
    /**
     * $day only carries date granularity (no time-of-day) - grouping is by
     * day, and Eloquent's date-cast read path (asDateTime) can resolve a
     * plain "Y-m-d" string straight to a Carbon instance without needing a
     * DB connection, unlike a full datetime string. That connection isn't
     * available here since this is a plain, non-Laravel-booted TestCase
     * (matching DomainCheckDriftTest's style, for a class with no I/O).
     */
    private function check(string $day, array $overrides = []): Check
    {
        $check = new Check();
        $check->setRawAttributes(array_merge([
            'created_at' => $day,
            'target_position' => null,
            'ai_overview_present' => false,
            'ai_overview_target_cited' => null,
            'lighthouse_performance' => null,
            'lighthouse_seo' => null,
            'lighthouse_accessibility' => null,
            'lighthouse_best_practices' => null,
        ], $overrides));

        return $check;
    }

    /**
     * Overrides for 'crux'/'gsc'/'ga4' must be passed as arrays (matching
     * the shape DomainCheckRunner writes) - they're JSON-encoded here
     * because setRawAttributes stores the *raw* (pre-cast) column value,
     * same as what actually comes back from the database.
     */
    private function domainCheck(string $day, array $overrides = []): DomainCheck
    {
        foreach (['crux', 'gsc', 'ga4'] as $jsonColumn) {
            if (array_key_exists($jsonColumn, $overrides)) {
                $overrides[$jsonColumn] = json_encode($overrides[$jsonColumn]);
            }
        }

        $check = new DomainCheck();
        $check->setRawAttributes(array_merge([
            'created_at' => $day,
            'crux' => null,
            'gsc' => null,
            'ga4' => null,
        ], $overrides));

        return $check;
    }

    public function test_empty_collection_returns_empty_series(): void
    {
        $result = (new ScoreHistoryBuilder())->groupedByDay(new Collection());

        $this->assertSame([], $result['labels']);
        $this->assertSame([], $result['ai_visibility']);
    }

    public function test_groups_multiple_checks_on_the_same_day(): void
    {
        $checks = new Collection([
            $this->check('2026-07-10', ['lighthouse_performance' => 80, 'target_position' => 4]),
            $this->check('2026-07-10', ['lighthouse_performance' => 90, 'target_position' => 6]),
        ]);

        $result = (new ScoreHistoryBuilder())->groupedByDay($checks);

        $this->assertSame(['2026-07-10'], $result['labels']);
        $this->assertSame([85.0], $result['lighthouse_performance']);
        $this->assertSame([5.0], $result['average_position']);
    }

    public function test_orders_days_chronologically_regardless_of_input_order(): void
    {
        $checks = new Collection([
            $this->check('2026-07-12'),
            $this->check('2026-07-10'),
            $this->check('2026-07-11'),
        ]);

        $result = (new ScoreHistoryBuilder())->groupedByDay($checks);

        $this->assertSame(['2026-07-10', '2026-07-11', '2026-07-12'], $result['labels']);
    }

    public function test_ai_visibility_score_is_share_of_present_checks_that_cite_the_domain(): void
    {
        $checks = new Collection([
            $this->check('2026-07-10', ['ai_overview_present' => true, 'ai_overview_target_cited' => true]),
            $this->check('2026-07-10', ['ai_overview_present' => true, 'ai_overview_target_cited' => false]),
            $this->check('2026-07-10', ['ai_overview_present' => false]),
        ]);

        $result = (new ScoreHistoryBuilder())->groupedByDay($checks);

        // 1 cited out of 2 checks where AI Overview was present; the check
        // without an AI Overview at all must not dilute the ratio.
        $this->assertSame([50.0], $result['ai_visibility']);
    }

    public function test_ai_visibility_score_is_null_when_ai_overview_never_observed(): void
    {
        $checks = new Collection([
            $this->check('2026-07-10', ['ai_overview_present' => false]),
        ]);

        $result = (new ScoreHistoryBuilder())->groupedByDay($checks);

        $this->assertSame([null], $result['ai_visibility']);
    }

    public function test_null_lighthouse_values_are_excluded_from_the_average_instead_of_counted_as_zero(): void
    {
        $checks = new Collection([
            $this->check('2026-07-10', ['lighthouse_performance' => 60]),
            $this->check('2026-07-10', ['lighthouse_performance' => null]),
        ]);

        $result = (new ScoreHistoryBuilder())->groupedByDay($checks);

        $this->assertSame([60.0], $result['lighthouse_performance']);
    }

    public function test_domain_check_history_extracts_crux_gsc_and_ga4_series(): void
    {
        $checks = new Collection([
            $this->domainCheck('2026-07-10', [
                'crux' => ['metrics' => [
                    'largest_contentful_paint' => ['label' => 'LCP', 'p75' => 2000, 'rating' => 'good'],
                    'cumulative_layout_shift' => ['label' => 'CLS', 'p75' => 0.05, 'rating' => 'good'],
                ]],
                'gsc' => ['clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'average_position' => 8.0],
                'ga4' => ['total_sessions' => 500, 'organic_sessions' => 300, 'active_users' => 200],
            ]),
            $this->domainCheck('2026-07-11', [
                'crux' => ['metrics' => [
                    'largest_contentful_paint' => ['label' => 'LCP', 'p75' => 3000, 'rating' => 'needs_improvement'],
                ]],
                'gsc' => ['clicks' => 200, 'impressions' => 2000, 'ctr' => 0.2, 'average_position' => 4.0],
            ]),
        ]);

        $result = (new ScoreHistoryBuilder())->domainCheckHistory($checks);

        $this->assertSame(['2026-07-10', '2026-07-11'], $result['labels']);
        $this->assertSame([2000.0, 3000.0], $result['crux_lcp']);
        // Only the first day reported CLS - the second day must stay null,
        // not silently become 0 and drag down a future average.
        $this->assertSame([0.05, null], $result['crux_cls']);
        $this->assertSame([100.0, 200.0], $result['gsc_clicks']);
        $this->assertSame([10.0, 20.0], $result['gsc_ctr']);
        $this->assertSame([500.0, null], $result['ga4_total_sessions']);
    }

    public function test_domain_check_history_ignores_null_json_columns(): void
    {
        $checks = new Collection([
            $this->domainCheck('2026-07-10'),
            $this->domainCheck('2026-07-11'),
        ]);

        $result = (new ScoreHistoryBuilder())->domainCheckHistory($checks);

        $this->assertSame(['2026-07-10', '2026-07-11'], $result['labels']);
        $this->assertSame([null, null], $result['crux_lcp']);
        $this->assertSame([null, null], $result['gsc_clicks']);
        $this->assertSame([null, null], $result['ga4_total_sessions']);
    }
}
