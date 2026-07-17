<?php

namespace Tests\Feature;

use App\Models\Check;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_non_admin_dashboard_only_lists_their_own_domains(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);

        Domain::query()->create(['domain' => 'alpha.example.com', 'user_id' => $user->id]);
        Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('alpha.example.com');
        $response->assertDontSee('beta.example.com');
    }

    public function test_non_admin_cannot_access_another_users_domain_resources(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $this->actingAs($user)->get("/domains/{$domain->id}")->assertStatus(403);
        $this->actingAs($user)->post("/domains/{$domain->id}/check")->assertStatus(403);
        $this->actingAs($user)->post("/domains/{$domain->id}/keywords", ['keyword' => 'seo'])->assertStatus(403);
        $this->actingAs($user)->delete("/domains/{$domain->id}")->assertStatus(403);
    }

    public function test_non_admin_cannot_access_another_users_keyword_or_check(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);
        $keyword = Keyword::query()->create([
            'domain_id' => $domain->id,
            'keyword' => 'beta keyword',
            'url' => 'https://beta.example.com/page',
        ]);
        $check = Check::query()->create([
            'keyword_id' => $keyword->id,
            'lighthouse_raw' => ['categories' => []],
        ]);

        $this->actingAs($user)->get("/keywords/{$keyword->id}")->assertStatus(403);
        $this->actingAs($user)->post("/keywords/{$keyword->id}/check")->assertStatus(403);
        $this->actingAs($user)->delete("/keywords/{$keyword->id}")->assertStatus(403);
        $this->actingAs($user)->get("/checks/{$check->id}/lighthouse.json")->assertStatus(403);
    }

    public function test_admin_can_access_other_users_domains(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $response = $this->actingAs($admin)->get("/domains/{$domain->id}");

        $response->assertOk();
        $response->assertSee('beta.example.com');
    }

    public function test_two_different_users_can_each_track_the_same_domain(): void
    {
        $owner = User::factory()->create(['approved_at' => now()]);
        $other = User::factory()->create(['approved_at' => now()]);
        Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $owner->id]);

        $response = $this->actingAs($other)->post('/domains', ['domain' => 'shared.example.com']);

        $newDomain = Domain::query()->where('user_id', $other->id)->where('domain', 'shared.example.com')->first();
        $this->assertNotNull($newDomain, 'the second user should get their own domain record, not be blocked by the first user\'s');

        $response->assertRedirect(route('domains.show', $newDomain));
        $this->actingAs($other)->get(route('domains.show', $newDomain))->assertOk();
    }

    public function test_adding_a_domain_already_tracked_by_the_same_user_redirects_without_duplicating(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'shared.example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/domains', ['domain' => 'shared.example.com']);

        $response->assertRedirect(route('domains.show', $domain));
        $this->assertSame(1, Domain::query()->where('domain', 'shared.example.com')->count());
    }

    public function test_admin_sees_domain_owner_in_dashboard_and_detail_page(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $owner = User::factory()->create(['approved_at' => now(), 'email' => 'owner@example.com']);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $owner->id]);

        $this->actingAs($admin)->get('/')->assertSee('owner@example.com');
        $this->actingAs($admin)->get("/domains/{$domain->id}")->assertSee('owner@example.com');
    }

    public function test_non_admin_does_not_see_an_owner_column(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'email' => 'me@example.com']);
        Domain::query()->create(['domain' => 'alpha.example.com', 'user_id' => $user->id]);

        $this->actingAs($user)->get('/')->assertDontSee('Sahibi');
    }
}
