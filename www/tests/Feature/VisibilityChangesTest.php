<?php

namespace Tests\Feature;

use App\Mcp\McpScopes;
use App\Mcp\Servers\SeoCheckerServer;
use App\Mcp\Tools\GetVisibilityChanges;
use App\Models\Domain;
use App\Models\User;
use App\Services\Analytics\VisibilityChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VisibilityChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_compares_latest_check_with_one_before_the_window(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $cited = $domain->keywords()->create(['keyword' => 'koşu ayakkabısı']);
        $ranked = $domain->keywords()->create(['keyword' => 'trail ayakkabı']);
        $stale = $domain->keywords()->create(['keyword' => 'eski kelime']);

        $this->travelTo(now()->subDays(10));
        $cited->checks()->create(['ai_overview_present' => true, 'ai_overview_target_cited' => true, 'target_position' => 3]);
        $ranked->checks()->create(['target_position' => 4]);
        $stale->checks()->create(['target_position' => 1]);
        $domain->promptExplorations()->create([
            'user_id' => $user->id, 'prompt' => 'p', 'providers' => ['openai'], 'completed_at' => now(),
            'results' => ['openai' => ['present' => true, 'response' => 'example.com', 'error' => null]],
        ]);
        $this->travelBack();

        $this->travelTo(now()->subDay());
        $cited->checks()->create(['ai_overview_present' => true, 'ai_overview_target_cited' => false, 'target_position' => 3]);
        $ranked->checks()->create(['target_position' => 12]);
        // Engellenmis kontrol hesaba katilmaz.
        $ranked->checks()->create(['blocked' => true]);
        $domain->promptExplorations()->create([
            'user_id' => $user->id, 'prompt' => 'p', 'providers' => ['openai'], 'completed_at' => now(),
            'results' => ['openai' => ['present' => false, 'response' => 'rival.com', 'error' => null]],
        ]);
        $this->travelBack();

        $result = app(VisibilityChanges::class)->forDomain($domain);

        $this->assertSame(2, $result['compared_keywords']);
        $this->assertSame(
            ['ai_overview_citation_lost', 'assistant_mentions_dropped', 'position_dropped'],
            array_column($result['losses'], 'type'),
        );
        $this->assertSame(4, $result['losses'][2]['position_before']);
        $this->assertSame(12, $result['losses'][2]['position_after']);
        $this->assertSame([], $result['gains']);

        Passport::actingAs($user, [McpScopes::READ]);
        SeoCheckerServer::tool(GetVisibilityChanges::class, ['domain' => 'example.com', 'days' => 7])
            ->assertOk()
            ->assertSee('ai_overview_citation_lost')
            ->assertSee('"mention_rate_after_pct":0');
    }

    public function test_no_baseline_returns_empty_result_with_note(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->keywords()->create(['keyword' => 'yeni'])->checks()->create(['target_position' => 5]);

        Passport::actingAs($user, [McpScopes::READ]);
        SeoCheckerServer::tool(GetVisibilityChanges::class, ['domain' => 'example.com'])
            ->assertOk()
            ->assertSee('"compared_keywords":0')
            ->assertSee('nothing could be compared');
    }
}
