<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_unapproved_user_is_redirected_to_pending_approval(): void
    {
        $user = User::factory()->create(['approved_at' => null]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect('/pending-approval');
    }

    public function test_unapproved_user_can_see_pending_approval_page(): void
    {
        $user = User::factory()->create(['approved_at' => null]);

        $response = $this->actingAs($user)->get('/pending-approval');

        $response->assertStatus(200);
    }

    public function test_approved_user_can_access_dashboard(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_approve_a_pending_user(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $pending = User::factory()->create(['approved_at' => null]);

        $this->actingAs($admin)->patch("/admin/users/{$pending->id}/approve");

        $this->assertNotNull($pending->fresh()->approved_at);
    }

    public function test_admin_cannot_revoke_their_own_approval(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$admin->id}/revoke");

        $response->assertStatus(403);
    }
}
