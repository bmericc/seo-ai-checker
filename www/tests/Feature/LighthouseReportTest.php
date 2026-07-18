<?php

namespace Tests\Feature;

use App\Jobs\RunSitemapUrlLighthouseCheck;
use App\Models\Domain;
use App\Models\SitemapUrl;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LighthouseReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_starting_a_scan_queues_a_job_per_active_sitemap_url(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $active = SitemapUrl::query()->create([
            'domain_id' => $domain->id,
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        SitemapUrl::query()->create([
            'domain_id' => $domain->id,
            'url' => 'https://example.com/removed',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'removed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('domains.lighthouse-report.start', $domain));

        $response->assertRedirect(route('domains.lighthouse-report', $domain));
        Queue::assertPushed(RunSitemapUrlLighthouseCheck::class, 1);
        Queue::assertPushed(fn (RunSitemapUrlLighthouseCheck $job) => $job->sitemapUrlId === $active->id);
    }

    public function test_a_scan_batch_is_capped_at_the_configured_limit(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        for ($i = 0; $i < 60; $i++) {
            SitemapUrl::query()->create([
                'domain_id' => $domain->id,
                'url' => "https://example.com/page-{$i}",
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $this->actingAs($user)->post(route('domains.lighthouse-report.start', $domain));

        Queue::assertPushed(RunSitemapUrlLighthouseCheck::class, 50);
    }

    public function test_report_page_shows_scores_and_pending_state(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        SitemapUrl::query()->create([
            'domain_id' => $domain->id,
            'url' => 'https://example.com/checked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'lighthouse_performance' => 87,
            'lighthouse_seo' => 95,
            'lighthouse_accessibility' => 91,
            'lighthouse_best_practices' => 100,
            'lighthouse_checked_at' => now(),
        ]);
        SitemapUrl::query()->create([
            'domain_id' => $domain->id,
            'url' => 'https://example.com/pending',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('domains.lighthouse-report', $domain));

        $response->assertOk();
        $response->assertSee('example.com/checked');
        $response->assertSee('87');
        $response->assertSee('example.com/pending');
        $response->assertSee('bekliyor');
    }

    public function test_non_admin_cannot_view_or_start_a_scan_on_another_users_domain(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $this->actingAs($user)->get(route('domains.lighthouse-report', $domain))->assertStatus(403);
        $this->actingAs($user)->post(route('domains.lighthouse-report.start', $domain))->assertStatus(403);
    }
}
