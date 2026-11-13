<?php

namespace Tests\Feature;

use App\Jobs\FetchCompetitorKeywords;
use App\Mcp\McpScopes;
use App\Mcp\Servers\SeoCheckerServer;
use App\Mcp\Tools\SetCompetitor;
use App\Models\CompetitorBlacklist;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CompetitorBlacklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_default_giants(): void
    {
        $this->assertSame(['facebook.com', 'google.com', 'youtube.com'], CompetitorBlacklist::domains());
    }

    public function test_matches_subdomains_but_not_lookalikes(): void
    {
        $this->assertTrue(CompetitorBlacklist::matches('m.facebook.com'));
        $this->assertTrue(CompetitorBlacklist::matches('www.youtube.com'));
        $this->assertFalse(CompetitorBlacklist::matches('notfacebook.com'));
        $this->assertFalse(CompetitorBlacklist::matches('google.com.tr'));
    }

    public function test_blacklisted_domains_are_left_out_of_serp_discovery(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'koşu ayakkabısı'])->checks()->create([
            'organic_results' => [
                ['position' => 1, 'url' => 'https://www.youtube.com/watch', 'domain' => 'youtube.com', 'title' => 'YT'],
                ['position' => 2, 'url' => 'https://m.facebook.com/x', 'domain' => 'm.facebook.com', 'title' => 'FB'],
                ['position' => 3, 'url' => 'https://rival.com/', 'domain' => 'rival.com', 'title' => 'Rival'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain))->assertOk();

        $this->assertSame(['rival.com'], array_column($response->viewData('competitorAnalysis')['competitors'], 'domain'));
    }

    public function test_blacklisted_domain_cannot_be_tracked_via_web_or_mcp(): void
    {
        Bus::fake([FetchCompetitorKeywords::class]);
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $this->actingAs($user)->post(route('domains.competitors.store', $domain), ['competitor_domain' => 'https://www.youtube.com/']);

        Passport::actingAs($user, [McpScopes::READ, McpScopes::WRITE]);
        SeoCheckerServer::tool(SetCompetitor::class, ['domain' => 'example.com', 'competitor' => 'facebook.com'])->assertHasErrors();

        $this->assertSame(0, $domain->competitors()->count());
        Bus::assertNotDispatched(FetchCompetitorKeywords::class);
    }

    public function test_admin_manages_blacklist_from_settings(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);

        $this->actingAs($admin)->get(route('settings'))->assertOk()->assertSee('Rakip kara listesi')->assertSee('youtube.com');

        $this->actingAs($admin)
            ->post(route('admin.competitor-blacklist.store'), ['domains' => "instagram.com, https://www.linkedin.com/in/x\nyoutube.com bad_domain"])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('flash', fn ($f) => str_contains($f['message'], 'instagram.com, linkedin.com') && str_contains($f['message'], 'bad_domain'));

        $this->assertContains('linkedin.com', CompetitorBlacklist::domains());

        $entry = CompetitorBlacklist::query()->where('domain', 'google.com')->sole();
        $this->actingAs($admin)->delete(route('admin.competitor-blacklist.destroy', $entry))->assertRedirect(route('settings'));
        $this->assertNotContains('google.com', CompetitorBlacklist::domains());
    }

    public function test_non_admin_cannot_see_or_change_blacklist(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($user)->get(route('settings'))->assertOk()->assertDontSee('Rakip kara listesi');
        $this->actingAs($user)->post(route('admin.competitor-blacklist.store'), ['domains' => 'example.org'])->assertForbidden();
        $this->assertNotContains('example.org', CompetitorBlacklist::domains());
    }
}
