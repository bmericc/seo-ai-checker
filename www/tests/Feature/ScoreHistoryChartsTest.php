<?php

namespace Tests\Feature;

use App\Models\Check;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreHistoryChartsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Check/DomainCheck::$fillable deliberately exclude created_at/updated_at
     * (only ever set by the database), so ->create(['created_at' => ...])
     * would silently drop it and Eloquent's own auto-timestamping would
     * then overwrite it with "now" anyway. Set it directly after creation
     * instead, which bypasses mass assignment.
     */
    private function backdate(Check|DomainCheck $check, \DateTimeInterface $when): Check|DomainCheck
    {
        $check->created_at = $when;
        $check->save();

        return $check;
    }

    public function test_domain_page_shows_ai_visibility_score_from_each_keywords_latest_check(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        // Snapshot only looks at each keyword's LATEST check, so an older
        // "not cited" result must not drag the score down once a newer
        // check cites the domain.
        $this->backdate($keyword->checks()->create([
            'ai_overview_present' => true,
            'ai_overview_target_cited' => false,
        ]), now()->subDay());
        $keyword->checks()->create([
            'ai_overview_present' => true,
            'ai_overview_target_cited' => true,
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Geçmiş Skorlar');
        $response->assertSee('AI Overview Görünürlük Skoru');
        $response->assertSee('100%');
        $response->assertSee('1 / 1 anahtar kelimede', false);
    }

    public function test_domain_page_renders_trend_charts_once_checks_span_multiple_days(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $this->backdate($keyword->checks()->create([
            'target_position' => 3,
            'lighthouse_performance' => 80,
        ]), now()->subDay());
        $keyword->checks()->create([
            'target_position' => 5,
            'lighthouse_performance' => 60,
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertDontSee('Grafikler için tamamlanmış en az bir kontrol gerekir.');
        $response->assertSee('<svg', false);
    }

    public function test_domain_page_renders_site_check_history_charts_once_domain_checks_span_multiple_days(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $this->backdate($domain->domainChecks()->create([
            'crux' => ['configured' => true, 'found' => true, 'origin' => 'https://example.com', 'metrics' => [
                'largest_contentful_paint' => ['label' => 'LCP', 'p75' => 2200, 'rating' => 'good'],
            ]],
            'gsc' => ['configured' => true, 'verified' => true, 'site_url' => 'https://example.com/', 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'average_position' => 6.0],
            'ga4' => ['configured' => true, 'property_id' => '123', 'total_sessions' => 400, 'organic_sessions' => 250, 'active_users' => 150],
        ]), now()->subDay());
        $domain->domainChecks()->create([
            'crux' => ['configured' => true, 'found' => true, 'origin' => 'https://example.com', 'metrics' => [
                'largest_contentful_paint' => ['label' => 'LCP', 'p75' => 2600, 'rating' => 'needs_improvement'],
            ]],
            'gsc' => ['configured' => true, 'verified' => true, 'site_url' => 'https://example.com/', 'clicks' => 120, 'impressions' => 1100, 'ctr' => 0.11, 'average_position' => 5.5],
            'ga4' => ['configured' => true, 'property_id' => '123', 'total_sessions' => 420, 'organic_sessions' => 260, 'active_users' => 160],
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Site Kontrolü Geçmişi');
        $response->assertSee('CrUX yükleme süresi trendi (ms)');
        $response->assertSee('GA4 oturum ve kullanıcı trendi');
    }

    public function test_domain_page_without_any_check_history_shows_empty_state_instead_of_error(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Henüz hiçbir anahtar kelimede AI Overview gözlemlenmedi.');
        $response->assertSee('Grafikler için tamamlanmış en az bir kontrol gerekir.');
        $response->assertDontSee('Site Kontrolü Geçmişi');
    }

    public function test_keyword_page_renders_score_trend_charts_when_checked_on_multiple_days(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $this->backdate($keyword->checks()->create([
            'target_position' => 8,
            'lighthouse_performance' => 50,
        ]), now()->subDay());
        $keyword->checks()->create(['target_position' => 4, 'lighthouse_performance' => 70]);

        $response = $this->actingAs($user)->get(route('keywords.show', $keyword));

        $response->assertOk();
        $response->assertSee('Geçmiş Skor Trendi');
        $response->assertSee('<svg', false);
    }

    public function test_keyword_page_with_a_single_check_still_shows_the_trend_card(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);
        $keyword->checks()->create(['target_position' => 4, 'lighthouse_performance' => 70]);

        $response = $this->actingAs($user)->get(route('keywords.show', $keyword));

        $response->assertOk();
        $response->assertSee('Geçmiş Skor Trendi');
        // Tek nokta icin polyline yerine yalnizca bir <circle> noktasi
        // cizilir - cizgi olusmasa da veri gorsellestirilir.
        $response->assertSee('<circle', false);
    }

    public function test_domain_page_with_a_single_check_still_shows_the_trend_charts(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);
        $keyword->checks()->create(['target_position' => 4, 'lighthouse_performance' => 70]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertDontSee('Grafikler için tamamlanmış en az bir kontrol gerekir.');
        $response->assertSee('<circle', false);
    }
}
