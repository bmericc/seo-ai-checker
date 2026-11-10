<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ReportShare;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function ownerWithDomain(): array
    {
        $owner = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);
        $domain->keywords()->create(['keyword' => 'örnek kelime']);

        return [$owner, $domain];
    }

    public function test_owner_can_create_a_share_link_with_selected_sections(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();

        $this->actingAs($owner)->post(route('domains.shares.store', $domain), [
            'title' => 'Ekim raporu',
            'sections' => ['summary', 'keywords'],
            'expires_in' => 7,
        ])->assertRedirect(route('domains.show', $domain));

        $share = $domain->reportShares()->first();
        $this->assertSame(['summary', 'keywords'], $share->sections);
        $this->assertSame('Ekim raporu', $share->title);
        $this->assertSame(40, strlen($share->token));
        $this->assertTrue($share->expires_at->between(now()->addDays(6), now()->addDays(8)));
    }

    public function test_public_report_is_visible_without_login_and_counts_views(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $share = $domain->reportShares()->create([
            'user_id' => $owner->id,
            'token' => ReportShare::newToken(),
            'sections' => ['keywords'],
        ]);

        $response = $this->get(route('reports.public', $share->token));

        $response->assertOk()
            ->assertSee('örnek kelime')
            ->assertSee('noindex', false)
            ->assertDontSee(route('logout'), false);
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame(1, $share->fresh()->view_count);
    }

    public function test_hidden_sections_are_not_rendered(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $share = $domain->reportShares()->create([
            'user_id' => $owner->id,
            'token' => ReportShare::newToken(),
            'sections' => ['summary'],
        ]);

        $this->get(route('reports.public', $share->token))
            ->assertOk()
            ->assertDontSee('örnek kelime');
    }

    public function test_revoked_and_expired_links_are_gone(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $revoked = $domain->reportShares()->create(['user_id' => $owner->id, 'token' => ReportShare::newToken(), 'sections' => ['summary'], 'revoked_at' => now()]);
        $expired = $domain->reportShares()->create(['user_id' => $owner->id, 'token' => ReportShare::newToken(), 'sections' => ['summary'], 'expires_at' => now()->subDay()]);

        $this->get(route('reports.public', $revoked->token))->assertStatus(410);
        $this->get(route('reports.public', $expired->token))->assertStatus(410);
        $this->get('/r/' . str_repeat('a', 40))->assertNotFound();
    }

    public function test_owner_can_revoke_a_link(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $share = $domain->reportShares()->create(['user_id' => $owner->id, 'token' => ReportShare::newToken(), 'sections' => ['summary']]);

        $this->actingAs($owner)->delete(route('domains.shares.destroy', [$domain, $share]))->assertRedirect();

        $this->assertNotNull($share->fresh()->revoked_at);
    }

    public function test_other_users_cannot_create_or_revoke_links(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $other = User::factory()->create(['approved_at' => now()]);
        $share = $domain->reportShares()->create(['user_id' => $owner->id, 'token' => ReportShare::newToken(), 'sections' => ['summary']]);

        $this->actingAs($other)->post(route('domains.shares.store', $domain), ['sections' => ['summary']])->assertForbidden();
        $this->actingAs($other)->delete(route('domains.shares.destroy', [$domain, $share]))->assertForbidden();
        $this->assertNull($share->fresh()->revoked_at);
    }
}
