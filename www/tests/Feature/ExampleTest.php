<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Ana sayfa girissiz de erisilebilir olmali (Google OAuth dogrulamasi
     * bunu gerektirir): login'e yonlendirmek yerine dogrudan aciklama
     * sayfasini gostermeli.
     */
    public function test_home_page_is_accessible_without_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Panel islevleri (domain/anahtar kelime yonetimi) girissiz erisime
     * kapali kalmali.
     */
    public function test_guests_are_redirected_to_login_for_protected_routes(): void
    {
        $response = $this->get('/domains/1');

        $response->assertRedirect('/login');
    }
}
