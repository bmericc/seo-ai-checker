<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Check;
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
}
