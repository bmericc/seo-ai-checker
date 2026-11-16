<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainMember;
use App\Models\MobileLoginToken;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function approvedUser(array $attributes = []): User
    {
        return User::factory()->create(['approved_at' => now(), ...$attributes]);
    }

    private function mobileToken(User $user): string
    {
        Artisan::call('passport:keys', ['--force' => true]);

        return $this->postJson(route('api.v1.auth.token'), ['code' => MobileLoginToken::issueFor($user)])->json('token');
    }

    public function test_user_deletes_own_account_with_owned_domains_and_keeps_others_data(): void
    {
        $user = $this->approvedUser(['email' => 'me@example.test']);
        $other = $this->approvedUser();
        $mine = Domain::query()->create(['domain' => 'mine.com', 'user_id' => $user->id]);
        $mine->keywords()->create(['keyword' => 'seo']);
        $theirs = Domain::query()->create(['domain' => 'theirs.com', 'user_id' => $other->id]);
        $theirs->members()->create(['user_id' => $user->id, 'email' => $user->email, 'permission' => 'read']);
        $pendingInvite = Domain::query()->create(['domain' => 'invite.com', 'user_id' => $other->id]);
        $pendingInvite->members()->create(['email' => 'me@example.test', 'permission' => 'read']);

        $this->actingAs($user)
            ->delete(route('account.destroy'), ['email' => 'ME@example.test '])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertModelMissing($mine);
        $this->assertDatabaseMissing('keywords', ['domain_id' => $mine->id]);
        $this->assertModelExists($theirs);
        $this->assertSame(0, DomainMember::query()->count());
        $this->assertModelExists($other);
    }

    public function test_wrong_email_confirmation_keeps_the_account(): void
    {
        $user = $this->approvedUser();

        $this->actingAs($user)
            ->from(route('account.delete'))
            ->delete(route('account.destroy'), ['email' => 'someone@else.test'])
            ->assertRedirect(route('account.delete'))
            ->assertSessionHasErrors('email');

        $this->assertModelExists($user);
    }

    public function test_pending_and_unverified_users_can_reach_the_delete_page(): void
    {
        $pending = User::factory()->create(['approved_at' => null]);
        $this->actingAs($pending)->get(route('pending-approval'))->assertSee(route('account.delete'), false);
        $this->actingAs($pending)->get(route('account.delete'))->assertOk()->assertSee($pending->email);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('account.delete'))->assertOk();
        $this->actingAs($unverified)->delete(route('account.destroy'), ['email' => $unverified->email])->assertRedirect('/');
        $this->assertModelMissing($unverified);
    }

    public function test_only_admin_cannot_delete_own_account(): void
    {
        $admin = $this->approvedUser(['is_admin' => true]);

        $this->actingAs($admin)->get(route('account.delete'))->assertOk()->assertDontSee(route('account.destroy'), false);
        $this->actingAs($admin)->delete(route('account.destroy'), ['email' => $admin->email])->assertForbidden();
        $this->assertModelExists($admin);

        $this->approvedUser(['is_admin' => true]);
        $this->actingAs($admin)->delete(route('account.destroy'), ['email' => $admin->email])->assertRedirect('/');
        $this->assertModelMissing($admin);
    }

    public function test_settings_page_links_to_account_deletion(): void
    {
        $this->actingAs($this->approvedUser())->get(route('settings'))->assertSee(route('account.delete'), false);
    }

    public function test_mobile_app_deletes_account_and_its_tokens(): void
    {
        $user = $this->approvedUser();
        $domain = Domain::query()->create(['domain' => 'mine.com', 'user_id' => $user->id]);
        $token = $this->mobileToken($user);

        for ($request = 0; $request < 10; $request++) {
            $this->withToken($token)->getJson(route('api.v1.me'))->assertOk();
        }

        $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['email' => 'wrong@example.test'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'confirmation_required');

        $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['email' => $user->email])->assertNoContent();

        $this->assertModelMissing($user);
        $this->assertModelMissing($domain);
        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    public function test_non_mobile_tokens_cannot_delete_the_account(): void
    {
        Artisan::call('passport:keys', ['--force' => true]);
        $user = $this->approvedUser();
        $token = $user->createToken('Some MCP client', ['read'])->accessToken;

        $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['email' => $user->email])
            ->assertForbidden()
            ->assertJsonPath('error', 'insufficient_scope');

        $this->assertModelExists($user);
    }
}
