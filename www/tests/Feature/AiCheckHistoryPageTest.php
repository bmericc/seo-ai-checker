<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Otomatik kelime kontrolundeki AI sonuclari ile Prompt Explorer / manuel
 * MCP kontrolleri tek kaynaktan (prompt_explorations) gosterilir.
 */
class AiCheckHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_ai_check_appears_once_in_keyword_and_domain_history(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'llm_visibility_enabled' => true]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);
        $check = $keyword->checks()->create(['target_position' => 3]);
        $domain->promptExplorations()->create([
            'keyword_id' => $keyword->id,
            'check_id' => $check->id,
            'prompt' => 'örnek kelime konusunda hangi web sitelerine bakmalıyım?',
            'providers' => ['openai'],
            'results' => ['openai' => ['present' => true, 'response' => 'example.com', 'error' => null, 'model' => 'gpt-test']],
            'completed_at' => now(),
        ]);

        $keywordPage = $this->actingAs($user)->get(route('keywords.show', $keyword));
        $keywordPage->assertOk()
            ->assertSee('Yapay zekâ kontrol geçmişi')
            ->assertSee('Otomatik')
            ->assertDontSee('Genel cevap:');
        $this->assertSame(1, substr_count($keywordPage->getContent(), 'örnek kelime konusunda hangi web sitelerine bakmalıyım?'));

        $this->actingAs($user)->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee('örnek kelime konusunda hangi web sitelerine bakmalıyım?')
            ->assertSee('gpt-test');
    }
}
