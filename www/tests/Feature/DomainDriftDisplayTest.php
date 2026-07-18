<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainDriftDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_page_shows_drift_when_two_checks_differ(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $domain->domainChecks()->create([
            'sitemap' => ['found' => true, 'is_valid_xml' => true, 'is_sitemap_index' => false, 'url_count' => 5, 'url' => 'https://example.com/sitemap.xml'],
        ]);
        $domain->domainChecks()->create([
            'sitemap' => ['found' => false, 'url' => 'https://example.com/sitemap.xml'],
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Bir önceki kontrole göre değişenler');
        $response->assertSee('Sitemap artık bulunamıyor');
    }

    public function test_domain_page_shows_no_drift_section_with_only_one_check(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->domainChecks()->create(['sitemap' => ['found' => true, 'is_valid_xml' => true, 'is_sitemap_index' => false, 'url_count' => 5, 'url' => 'https://example.com/sitemap.xml']]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertDontSee('Bir önceki kontrole göre değişenler');
    }

    public function test_domain_page_shows_no_drift_section_when_checks_are_identical(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $sitemap = ['found' => true, 'is_valid_xml' => true, 'is_sitemap_index' => false, 'url_count' => 5, 'url' => 'https://example.com/sitemap.xml'];
        $domain->domainChecks()->create(['sitemap' => $sitemap]);
        $domain->domainChecks()->create(['sitemap' => $sitemap]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertDontSee('Bir önceki kontrole göre değişenler');
    }
}
