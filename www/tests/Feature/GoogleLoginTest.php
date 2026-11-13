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

    private function socialiteUser(string $email, string $googleId = 'google-1', ?string $locale = null): SocialiteUser
    {
        $user = new SocialiteUser();
        $user->id = $googleId;
        $user->email = $email;
        $user->name = 'Test User';
        $user->token = 'access-token';
        $user->refreshToken = 'refresh-token';
        $user->expiresIn = 3600;
        $user->user = $locale !== null ? ['locale' => $locale] : [];

        return $user;
    }

    public function test_callback_creates_a_new_user_and_stores_google_tokens(): void
    {
        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn($this->socialiteUser('new@example.com'));

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

        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn($this->socialiteUser('user@example.com', 'google-2'));

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('access-token', $existing->fresh()->google_access_token);
    }

    public function test_new_user_gets_the_browser_language_not_the_google_profile_language(): void
    {
        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn(
            $this->socialiteUser('new@example.com', locale: 'en-GB'),
        );

        $this->withHeaders(['Accept-Language' => 'tr-TR,tr;q=0.9'])->get(route('google.callback'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('tr', $user->locale);
    }

    public function test_new_user_with_a_non_turkish_browser_gets_english(): void
    {
        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn(
            $this->socialiteUser('new@example.com'),
        );

        $this->withHeaders(['Accept-Language' => 'de-DE'])->get(route('google.callback'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('en', $user->locale);
    }

    public function test_login_keeps_the_language_the_user_chose(): void
    {
        $existing = User::factory()->create([
            'email' => 'user@example.com',
            'approved_at' => now(),
            'locale' => 'tr',
        ]);

        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn(
            $this->socialiteUser('user@example.com', 'google-2', locale: 'en'),
        );

        $this->withHeaders(['Accept-Language' => 'en-US'])->get(route('google.callback'));

        $this->assertSame('tr', $existing->fresh()->locale);
    }

    public function test_each_host_uses_its_own_callback_url(): void
    {
        foreach (['seo-checker.tr', 'seo-checker.tarti.com'] as $host) {
            $location = (string) $this->get("https://{$host}/auth/google")->headers->get('Location');
            parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

            $this->assertStringStartsWith('https://accounts.google.com/', $location);
            $this->assertSame("https://{$host}/auth/google/callback", $query['redirect_uri']);
        }
    }
}
