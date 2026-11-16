<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_is_public_and_covers_the_mobile_app_in_turkish(): void
    {
        $this->withHeaders(['Accept-Language' => 'tr-TR'])
            ->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Firebase Crashlytics')
            ->assertSee('reklam kimliği (IDFA / Android Advertising ID)', false);
    }

    public function test_privacy_policy_is_public_and_covers_the_mobile_app_in_english(): void
    {
        $this->withHeaders(['Accept-Language' => 'en-US'])
            ->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Google Analytics for Firebase')
            ->assertSee('does not collect the advertising identifier', false);
    }

    public function test_terms_of_service_is_public_in_both_languages(): void
    {
        $this->withHeaders(['Accept-Language' => 'tr-TR'])
            ->get('/terms-of-service')
            ->assertOk()
            ->assertSee('SEO Checker')
            ->assertSee(route('privacy-policy'), false);

        $this->withHeaders(['Accept-Language' => 'en-US'])
            ->get('/terms-of-service')
            ->assertOk()
            ->assertSee('Governing law');
    }
}
