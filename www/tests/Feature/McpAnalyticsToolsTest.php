<?php

namespace Tests\Feature;

use App\Mcp\McpScopes;
use App\Mcp\Servers\SeoCheckerServer;
use App\Mcp\Tools\GenerateLlmsTxt;
use App\Mcp\Tools\GetCompetitors;
use App\Mcp\Tools\GetDomain;
use App\Mcp\Tools\ListKeywords;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpAnalyticsToolsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDomain(): array
    {
        $user = User::factory()->create(['approved_at' => now()]);
        Passport::actingAs($user, [McpScopes::READ]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        return [$user, $domain];
    }

    public function test_read_only_tokens_see_the_new_tools(): void
    {
        $this->setUpDomain();

        SeoCheckerServer::tools()->assertRegistered([GetCompetitors::class, GenerateLlmsTxt::class]);
    }

    public function test_get_domain_and_list_keywords_include_ai_overview_stability(): void
    {
        [, $domain] = $this->setUpDomain();
        $keyword = $domain->keywords()->create(['keyword' => 'koşu ayakkabısı']);
        $keyword->checks()->create(['ai_overview_present' => true, 'ai_overview_target_cited' => true]);

        SeoCheckerServer::tool(GetDomain::class, ['domain' => 'example.com'])
            ->assertOk()->assertSee('"ai_overview_stability"')->assertSee('"avg_citation_rate_pct":100');
        SeoCheckerServer::tool(ListKeywords::class, ['domain' => 'example.com'])
            ->assertOk()->assertSee('"change_rate_pct":null')->assertSee('"ai_overview_rate_pct":100');
    }

    public function test_get_competitors_returns_serp_and_share_of_model(): void
    {
        [$user, $domain] = $this->setUpDomain();
        $domain->keywords()->create(['keyword' => 'koşu'])->checks()->create([
            'organic_results' => [
                ['position' => 1, 'url' => 'https://rival.com/', 'domain' => 'rival.com', 'title' => 'Rival'],
                ['position' => 4, 'url' => 'https://example.com/', 'domain' => 'example.com', 'title' => 'Us'],
            ],
        ]);
        $domain->promptExplorations()->create([
            'user_id' => $user->id,
            'prompt' => 'en iyi koşu ayakkabısı',
            'providers' => ['openai'],
            'results' => ['openai' => ['present' => true, 'response' => 'example.com ve rival.com', 'error' => null]],
            'completed_at' => now(),
        ]);

        SeoCheckerServer::tool(GetCompetitors::class, ['domain' => 'example.com'])
            ->assertOk()
            ->assertSee('"shared_keywords":1')
            ->assertSee('"is_own_site":true')
            ->assertSee('"ChatGPT":100');
    }

    public function test_generate_llms_txt_returns_draft_or_error_without_sitemap(): void
    {
        [, $domain] = $this->setUpDomain();

        SeoCheckerServer::tool(GenerateLlmsTxt::class, ['domain' => 'example.com'])->assertHasErrors();

        $domain->sitemapUrls()->create([
            'url' => 'https://example.com/blog/ilk-yazi',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        SeoCheckerServer::tool(GenerateLlmsTxt::class, ['domain' => 'example.com'])
            ->assertOk()
            ->assertSee('## Blog')
            ->assertSee('"page_count":1');
    }

    public function test_other_users_domain_is_not_reachable(): void
    {
        $owner = User::factory()->create(['approved_at' => now()]);
        Domain::query()->create(['domain' => 'secret.com', 'user_id' => $owner->id]);
        $this->setUpDomain();

        SeoCheckerServer::tool(GetCompetitors::class, ['domain' => 'secret.com'])->assertHasErrors();
        SeoCheckerServer::tool(GenerateLlmsTxt::class, ['domain' => 'secret.com'])->assertHasErrors();
    }
}
