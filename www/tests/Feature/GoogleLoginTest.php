<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function socialiteUser(string $email, string $googleId = 'google-1'): SocialiteUser
    {
        $user = new SocialiteUser();
        $user->id = $googleId;
        $user->email = $email;
        $user->name = 'Test User';
        $user->token = 'access-token';
        $user->refreshToken = 'refresh-token';
        $user->expiresIn = 3600;

        return $user;
    }

    public function test_callback_creates_a_new_user_and_stores_google_tokens(): void
    {
        Socialite::shouldReceive('driver->user')->andReturn($this->socialiteUser('new@example.com'));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('google-1', $user->google_id);
        $this->assertSame('access-token', $user->google_access_token);
        $this->assertSame('refresh-token', $user->google_refresh_token);
    }

    public function test_callback_logs_in_an_existing_user_and_refreshes_their_tokens(): void
    {
        $existing = User::factory()->create([
            'email' => 'user@example.com',
            'approved_at' => now(),
            'google_refresh_token' => 'old-refresh-token',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($this->socialiteUser('user@example.com', 'google-2'));

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('access-token', $existing->fresh()->google_access_token);
    }
}
