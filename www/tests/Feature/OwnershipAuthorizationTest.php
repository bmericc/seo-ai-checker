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
}
