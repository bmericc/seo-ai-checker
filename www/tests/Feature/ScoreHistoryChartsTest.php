<?php

namespace Tests\Feature;

use App\Models\Check;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreHistoryChartsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Check::$fillable deliberately excludes created_at/updated_at (only
     * ever set by the database), so ->create(['created_at' => ...]) would
     * silently drop it and Eloquent's own auto-timestamping would then
     * overwrite it with "now" anyway. Set it directly after creation
     * instead, which bypasses mass assignment.
     */
    private function backdate(Check $check, \DateTimeInterface $when): Check
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
        $response->assertDontSee('Grafikler için en az iki farklı günde tamamlanmış kontrol gerekir.');
        $response->assertSee('<svg', false);
    }

    public function test_domain_page_without_any_check_history_shows_empty_state_instead_of_error(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Henüz hiçbir anahtar kelimede AI Overview gözlemlenmedi.');
        $response->assertSee('Grafikler için en az iki farklı günde tamamlanmış kontrol gerekir.');
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

    public function test_keyword_page_with_a_single_check_hides_the_trend_card(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);
        $keyword->checks()->create(['target_position' => 4]);

        $response = $this->actingAs($user)->get(route('keywords.show', $keyword));

        $response->assertOk();
        $response->assertDontSee('Geçmiş Skor Trendi');
    }
}
