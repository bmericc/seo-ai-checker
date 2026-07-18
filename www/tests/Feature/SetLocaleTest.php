<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_turkish_when_no_accept_language_header_is_sent(): void
    {
        $response = $this->withHeaders(['Accept-Language' => ''])->get('/');

        $response->assertSee('Google ile giriş yap');
    }

    public function test_guest_gets_english_when_browser_prefers_english(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/');

        $response->assertSee('Sign in with Google');
    }

    public function test_guest_gets_turkish_when_browser_prefers_an_unsupported_language(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])->get('/');

        $response->assertSee('Google ile giriş yap');
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
