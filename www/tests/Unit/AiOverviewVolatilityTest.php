<?php

namespace Tests\Unit;

use App\Models\Check;
use App\Services\Analytics\AiOverviewVolatility;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiOverviewVolatilityTest extends TestCase
{
    /** @param list<array{0: bool, 1: bool, 2?: bool}> $states [present, cited, blocked] oldest first */
    private function checks(array $states): array
    {
        $checks = [];
        foreach ($states as $i => $state) {
            $checks[] = (new Check())->forceFill([
                'id' => $i + 1,
                'created_at' => Carbon::parse('2026-01-01')->addDays($i),
                'ai_overview_present' => $state[0],
                'ai_overview_target_cited' => $state[1],
                'blocked' => $state[2] ?? false,
            ]);
        }

        return array_reverse($checks);
    }

    public function test_empty_history_returns_nulls(): void
    {
        $result = AiOverviewVolatility::forChecks([]);

        $this->assertSame(0, $result['checks']);
        $this->assertNull($result['aio_rate']);
        $this->assertNull($result['level']);
    }

    public function test_stable_cited_history(): void
    {
        $result = AiOverviewVolatility::forChecks($this->checks(array_fill(0, 4, [true, true])));

        $this->assertSame(100, $result['aio_rate']);
        $this->assertSame(100, $result['citation_rate']);
        $this->assertSame(0, $result['change_rate']);
        $this->assertSame(AiOverviewVolatility::LEVEL_STABLE, $result['level']);
    }

    public function test_alternating_history_is_volatile(): void
    {
        // cited, absent, cited, present(not cited), cited
        $result = AiOverviewVolatility::forChecks($this->checks([
            [true, true], [false, false], [true, true], [true, false], [true, true],
        ]));

        $this->assertSame(80, $result['aio_rate']);
        $this->assertSame(75, $result['citation_rate']);
        $this->assertSame(100, $result['change_rate']);
        $this->assertSame(AiOverviewVolatility::LEVEL_HIGH, $result['level']);
    }

    public function test_blocked_checks_are_ignored(): void
    {
        $result = AiOverviewVolatility::forChecks($this->checks([
            [true, true], [false, false, true], [true, true],
        ]));

        $this->assertSame(2, $result['checks']);
        $this->assertSame(0, $result['change_rate']);
    }

    public function test_only_latest_window_is_used(): void
    {
        $states = array_merge(array_fill(0, 5, [false, false]), array_fill(0, AiOverviewVolatility::WINDOW, [true, false]));

        $result = AiOverviewVolatility::forChecks($this->checks($states));

        $this->assertSame(AiOverviewVolatility::WINDOW, $result['checks']);
        $this->assertSame(100, $result['aio_rate']);
        $this->assertSame(0, $result['citation_rate']);
    }
}
