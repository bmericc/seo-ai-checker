<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_page_lists_domains_that_recur_across_tracked_keywords(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $keywordA = $domain->keywords()->create(['keyword' => 'anahtar bir']);
        $keywordA->checks()->create([
            'organic_results' => [
                ['position' => 1, 'url' => 'https://rival.com/', 'domain' => 'rival.com', 'title' => 'Rival'],
                ['position' => 2, 'url' => 'https://example.com/', 'domain' => 'example.com', 'title' => 'Us'],
            ],
        ]);

        $keywordB = $domain->keywords()->create(['keyword' => 'anahtar iki']);
        $keywordB->checks()->create([
            'organic_results' => [
                ['position' => 3, 'url' => 'https://rival.com/urun', 'domain' => 'rival.com', 'title' => 'Rival'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Rakip Analizi');
        $response->assertSee('rival.com');
        // "2 / 2" - rival.com appears in both tracked keywords' latest SERP snapshot.
        $response->assertSee('2 / 2');
    }

    public function test_domain_page_shows_empty_state_without_any_serp_history(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Rakip analizi için en az bir anahtar kelimede engellenmemiş bir SERP kontrolü gerekir.');
    }
}
