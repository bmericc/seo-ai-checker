<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\DomainFact;
use App\Models\User;
use App\Services\Drift\DomainCheckDrift;
use App\Services\Report\WeeklySeoReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklySeoReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function builder(): WeeklySeoReportBuilder
    {
        return new WeeklySeoReportBuilder(new DomainCheckDrift());
    }

    public function test_returns_null_when_domain_has_no_keywords_or_domain_checks(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $report = $this->builder()->build($domain);

        $this->assertNull($report);
    }

    public function test_computes_trend_against_a_check_at_least_six_days_older(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $old = $keyword->checks()->create(['target_position' => 8, 'eeat_score' => 40]);
        \App\Models\Check::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
        $keyword->checks()->create(['target_position' => 5, 'eeat_score' => 60]);

        $report = $this->builder()->build($domain);

        $this->assertNotNull($report);
        $this->assertCount(1, $report['keywords']);
        $kw = $report['keywords'][0];
        $this->assertSame(5, $kw['position']);
        $this->assertSame(8, $kw['previous_position']);
        $this->assertSame(60, $kw['eeat_score']);
        $this->assertSame(40, $kw['previous_eeat_score']);
    }

    public function test_check_less_than_six_days_old_is_not_used_as_previous(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $old = $keyword->checks()->create(['target_position' => 8]);
        \App\Models\Check::whereKey($old->id)->update(['created_at' => now()->subDays(2)]);
        $keyword->checks()->create(['target_position' => 5]);

        $report = $this->builder()->build($domain);

        $this->assertNull($report['keywords'][0]['previous_position']);
    }

    public function test_aggregates_average_position_and_ai_overview_counts(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $k1 = $domain->keywords()->create(['keyword' => 'birinci']);
        $k1->checks()->create(['target_position' => 2, 'ai_overview_present' => true, 'ai_overview_target_cited' => true]);

        $k2 = $domain->keywords()->create(['keyword' => 'ikinci']);
        $k2->checks()->create(['target_position' => 6, 'ai_overview_present' => true, 'ai_overview_target_cited' => false]);

        $report = $this->builder()->build($domain);

        $this->assertSame(4.0, $report['aggregates']['avg_position']);
        $this->assertSame(2, $report['aggregates']['ai_overview_present_count']);
        $this->assertSame(1, $report['aggregates']['ai_overview_cited_count']);
    }

    public function test_llm_pass_counts_are_only_computed_when_llm_visibility_is_enabled_on_the_domain(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'llm_visibility_enabled' => true]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);
        $keyword->checks()->create([
            'target_position' => 3,
            'llm_visibility' => [
                'openai' => ['present' => true, 'response' => null, 'error' => null],
                'anthropic' => ['present' => false, 'response' => null, 'error' => null],
            ],
        ]);

        $report = $this->builder()->build($domain);

        $this->assertSame(1, $report['aggregates']['llm_pass_counts']['openai']);
        $this->assertSame(0, $report['aggregates']['llm_pass_counts']['anthropic']);
        $this->assertSame(1, $report['aggregates']['llm_checked_count']);
    }

    public function test_drift_changes_are_included_when_two_domain_checks_exist(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'örnek kelime'])->checks()->create(['target_position' => 3]);

        $domain->domainChecks()->create([
            'security_headers' => ['http_redirects_to_https' => false, 'headers' => []],
            'created_at' => now()->subDays(3),
        ]);
        $domain->domainChecks()->create([
            'security_headers' => ['http_redirects_to_https' => true, 'headers' => []],
            'created_at' => now(),
        ]);

        $report = $this->builder()->build($domain->fresh());

        $this->assertNotEmpty($report['drift_changes']);
    }

    public function test_fact_is_passed_through_for_site_check_badges(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'örnek kelime'])->checks()->create(['target_position' => 3]);
        DomainFact::query()->create(['domain' => 'example.com', 'security_headers' => ['http_redirects_to_https' => true]]);

        $report = $this->builder()->build($domain->fresh());

        $this->assertNotNull($report['fact']);
        $this->assertTrue($report['fact']->security_headers['http_redirects_to_https']);
    }
}
