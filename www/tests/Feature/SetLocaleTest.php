<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_english_when_no_accept_language_header_is_sent(): void
    {
        $response = $this->withHeaders(['Accept-Language' => ''])->get('/');

        $response->assertSee('Sign in with Google');
    }

    public function test_guest_gets_turkish_when_browser_prefers_turkish(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.8'])->get('/');

        $response->assertSee('Google ile giriş yap');
    }

    public function test_guest_gets_english_when_browser_prefers_english(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/');

        $response->assertSee('Sign in with Google');
    }

    public function test_guest_gets_english_when_browser_prefers_an_unsupported_language(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,tr;q=0.5'])->get('/');

        $response->assertSee('Sign in with Google');
    }

    public function test_guest_locale_cookie_overrides_browser_language(): void
    {
        $response = $this->withCookie('locale', 'tr')
            ->withHeaders(['Accept-Language' => 'en-US'])
            ->get('/');

        $response->assertSee('Google ile giriş yap');
    }

    public function test_guest_can_switch_language_and_gets_a_cookie(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->from('/')->post(route('locale.update'), ['locale' => 'tr']);

        $response->assertRedirect('/');
        $response->assertCookie('locale', 'tr');
    }

    public function test_switching_to_an_unsupported_language_is_rejected(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->from('/')->post(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_authenticated_user_can_change_language_in_settings(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $user = User::factory()->create(['approved_at' => now(), 'locale' => 'tr']);

        $this->actingAs($user)->get(route('settings'))->assertOk()->assertSee('Arayüz dili');

        $this->actingAs($user)->from(route('settings'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('settings'));

        $this->assertSame('en', $user->fresh()->locale);
        $this->actingAs($user->fresh())->get(route('settings'))->assertSee('Interface language');
    }

    public function test_authenticated_user_locale_overrides_browser_language(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'locale' => 'en']);

        $response = $this->actingAs($user)->withHeaders(['Accept-Language' => 'tr'])->get('/');

        $response->assertSee('Domains');
        $response->assertDontSee('Domainler');
    }

    public function test_authenticated_user_without_a_stored_locale_falls_back_to_browser_language(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'locale' => null]);

        $response = $this->actingAs($user)->withHeaders(['Accept-Language' => 'en'])->get('/');

        $response->assertSee('Domains');
    }

    public function test_html_lang_attribute_matches_the_resolved_locale(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en'])->get('/');

        $response->assertSee('<html lang="en"', false);
    }
}
