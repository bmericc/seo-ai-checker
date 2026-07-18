<?php

namespace Tests\Feature;

use App\Jobs\RunSitemapUrlLighthouseCheck;
use App\Jobs\RunSitemapUrlOnPageCheck;
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

        $this->assertNotNull($active->fresh()->lighthouse_queued_at);
        $this->assertTrue($active->fresh()->isLighthouseQueued());

        $reportResponse = $this->actingAs($user)->get(route('domains.lighthouse-report', $domain));
        $reportResponse->assertSee('kuyrukta');
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

    public function test_starting_a_scan_reuses_a_fresh_sibling_lighthouse_result_instead_of_queuing(): void
    {
        Queue::fake();

        $userA = User::factory()->create(['approved_at' => now()]);
        $userB = User::factory()->create(['approved_at' => now()]);
        $domainA = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);

        $domainA->sitemapUrls()->create([
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'lighthouse_performance' => 77,
            'lighthouse_seo' => 88,
            'lighthouse_checked_at' => now()->subHours(2),
        ]);
        $target = $domainB->sitemapUrls()->create([
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($userB)->post(route('domains.lighthouse-report.start', $domainB));

        Queue::assertNotPushed(RunSitemapUrlLighthouseCheck::class);
        $this->assertSame(77, $target->fresh()->lighthouse_performance);
        $this->assertSame(88, $target->fresh()->lighthouse_seo);
    }

    public function test_starting_a_scan_queues_when_sibling_result_is_stale(): void
    {
        Queue::fake();

        $userA = User::factory()->create(['approved_at' => now()]);
        $userB = User::factory()->create(['approved_at' => now()]);
        $domainA = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);

        $domainA->sitemapUrls()->create([
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'lighthouse_performance' => 77,
            'lighthouse_checked_at' => now()->subHours(7),
        ]);
        $target = $domainB->sitemapUrls()->create([
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($userB)->post(route('domains.lighthouse-report.start', $domainB));

        Queue::assertPushed(RunSitemapUrlLighthouseCheck::class, 1);
        Queue::assertPushed(fn (RunSitemapUrlLighthouseCheck $job) => $job->sitemapUrlId === $target->id);
    }

    public function test_starting_an_onpage_scan_reuses_a_fresh_sibling_result_instead_of_queuing(): void
    {
        Queue::fake();

        $userA = User::factory()->create(['approved_at' => now()]);
        $userB = User::factory()->create(['approved_at' => now()]);
        $domainA = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);

        $domainA->sitemapUrls()->create([
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'onpage_data' => ['canonical_status' => 'self'],
            'onpage_checked_at' => now()->subHours(1),
        ]);
        $target = $domainB->sitemapUrls()->create([
            'url' => 'https://example.com/a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($userB)->post(route('domains.lighthouse-report.start-onpage', $domainB));

        Queue::assertNotPushed(RunSitemapUrlOnPageCheck::class);
        $this->assertSame('self', $target->fresh()->onpage_data['canonical_status']);
    }

    public function test_starting_an_onpage_scan_queues_a_job_per_active_sitemap_url(): void
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

        $response = $this->actingAs($user)->post(route('domains.lighthouse-report.start-onpage', $domain));

        $response->assertRedirect(route('domains.lighthouse-report', $domain));
        Queue::assertPushed(RunSitemapUrlOnPageCheck::class, 1);
        Queue::assertPushed(fn (RunSitemapUrlOnPageCheck $job) => $job->sitemapUrlId === $active->id);
        $this->assertTrue($active->fresh()->isOnPageQueued());
    }

    public function test_onpage_scan_batch_is_capped_at_the_configured_limit(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        for ($i = 0; $i < 250; $i++) {
            SitemapUrl::query()->create([
                'domain_id' => $domain->id,
                'url' => "https://example.com/page-{$i}",
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $this->actingAs($user)->post(route('domains.lighthouse-report.start-onpage', $domain));

        Queue::assertPushed(RunSitemapUrlOnPageCheck::class, 200);
    }

    public function test_report_page_shows_onpage_results(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        SitemapUrl::query()->create([
            'domain_id' => $domain->id,
            'url' => 'https://example.com/checked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'onpage_data' => [
                'canonical_status' => 'missing',
                'h1_count' => 2,
                'heading_hierarchy_skip' => true,
                'og_tags' => [],
                'schema_types' => ['Article', 'WPHeader'],
                'deprecated_schema_types' => ['WPHeader'],
            ],
            'onpage_checked_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('domains.lighthouse-report', $domain));

        $response->assertOk();
        $response->assertSee('example.com/checked');
        $response->assertSee('Article');
        $response->assertSee('WPHeader');
    }

    public function test_report_page_shows_hreflang_and_image_issues(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        SitemapUrl::query()->create([
            'domain_id' => $domain->id,
            'url' => 'https://example.com/checked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'onpage_data' => [
                'canonical_status' => 'self',
                'h1_count' => 1,
                'heading_hierarchy_skip' => false,
                'og_tags' => ['og:title' => 'x'],
                'schema_types' => [],
                'deprecated_schema_types' => [],
                'hreflang_tags' => ['en' => 'https://example.com/checked'],
                'hreflang_issues' => ['x-default eksik'],
                'image_stats' => ['total' => 3, 'missing_alt' => 2, 'missing_dimensions' => 1, 'not_lazy' => 0, 'legacy_format' => 0],
            ],
            'onpage_checked_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('domains.lighthouse-report', $domain));

        $response->assertOk();
        $response->assertSee('1 sorun');
        $response->assertSee('2 alt eksik');
        $response->assertSee('1 boyut eksik');
    }

    public function test_non_admin_cannot_start_an_onpage_scan_on_another_users_domain(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $this->actingAs($user)->post(route('domains.lighthouse-report.start-onpage', $domain))->assertStatus(403);
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
