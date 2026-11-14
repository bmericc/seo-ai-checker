<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainMember;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'new@example.com'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('register.store'), [
            'name' => 'New User',
            'email' => $email,
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ]);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_register_creates_an_unverified_unapproved_user_and_sends_verification(): void
    {
        Notification::fake();

        $this->register('New@Example.com')->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->isApproved());
        $this->assertTrue(Hash::check('secret-pass-123', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_register_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->register('taken@example.com')->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
    }

    public function test_unverified_user_is_sent_to_the_verification_page(): void
    {
        $user = User::factory()->unverified()->create(['approved_at' => now()]);

        $this->actingAs($user)->get('/')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);
    }

    public function test_verifying_email_leads_to_pending_approval(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect(route('pending-approval'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get('/')->assertRedirect(route('pending-approval'));
    }

    public function test_bootstrap_admin_email_is_promoted_only_after_verification(): void
    {
        config(['seo.bootstrap_admin_emails' => ['boss@example.com']]);
        Notification::fake();

        $this->register('boss@example.com');
        $user = User::query()->where('email', 'boss@example.com')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->isApproved());

        $this->get($this->verificationUrl($user));

        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->isApproved());
    }

    public function test_pending_domain_shares_are_claimed_only_after_verification(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['user_id' => $owner->id, 'domain' => 'shared.example']);
        DomainMember::query()->create([
            'domain_id' => $domain->id,
            'email' => 'friend@example.com',
            'permission' => 'read',
            'invited_by' => $owner->id,
        ]);

        $this->register('friend@example.com');
        $user = User::query()->where('email', 'friend@example.com')->firstOrFail();
        $this->assertNull(DomainMember::query()->firstOrFail()->user_id);

        $this->get($this->verificationUrl($user));

        $this->assertSame($user->id, DomainMember::query()->firstOrFail()->user_id);
    }

    public function test_login_with_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'approved_at' => now()]);

        $this->post(route('login.attempt'), ['email' => 'ME@example.com', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        User::factory()->create(['email' => 'me@example.com']);

        $this->post(route('login.attempt'), ['email' => 'me@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_google_only_user_cannot_log_in_with_a_password(): void
    {
        User::factory()->create(['email' => 'g@example.com', 'password' => null]);

        $this->post(route('login.attempt'), ['email' => 'g@example.com', 'password' => ''])
            ->assertSessionHasErrors('password');
        $this->post(route('login.attempt'), ['email' => 'g@example.com', 'password' => 'anything'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_too_many_attempts(): void
    {
        User::factory()->create(['email' => 'me@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['email' => 'me@example.com', 'password' => 'wrong']);
        }

        $this->post(route('login.attempt'), ['email' => 'me@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_sets_a_password_and_verifies_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'g@example.com', 'password' => null]);

        $this->post(route('password.email'), ['email' => 'g@example.com'])->assertSessionHas('flash');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'g@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_password_reset_request_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('flash');

        Notification::assertNothingSent();
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'me@example.com']);

        $this->post(route('password.update'), [
            'token' => 'bogus',
            'email' => 'me@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');
    }

    public function test_auth_pages_render(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'me@example.com']))
            ->assertOk()
            ->assertSee('me@example.com');
    }

    public function test_guests_hitting_protected_pages_go_to_login(): void
    {
        $this->get(route('connections'))->assertRedirect(route('login'));
    }
}
