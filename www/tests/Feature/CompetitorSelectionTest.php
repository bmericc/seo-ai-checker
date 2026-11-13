<?php

namespace Tests\Feature;

use App\Jobs\FetchCompetitorKeywords;
use App\Mcp\McpScopes;
use App\Mcp\Servers\SeoCheckerServer;
use App\Mcp\Tools\GetCompetitorKeywordGap;
use App\Mcp\Tools\GetCompetitors;
use App\Mcp\Tools\SetCompetitor;
use App\Models\Domain;
use App\Models\DomainCompetitor;
use App\Models\DomainMember;
use App\Models\User;
use App\Services\Competitors\CompetitorSiteCrawler;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\RankedKeywordsFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CompetitorSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{User, Domain}
     */
    private function domainWithSerp(): array
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $domain->keywords()->create(['keyword' => 'koşu ayakkabısı'])->checks()->create([
            'organic_results' => [
                ['position' => 1, 'url' => 'https://rival.com/', 'domain' => 'rival.com', 'title' => 'Rival'],
                ['position' => 2, 'url' => 'https://www.wikipedia.org/', 'domain' => 'wikipedia.org', 'title' => 'Wiki'],
                ['position' => 3, 'url' => 'https://example.com/', 'domain' => 'example.com', 'title' => 'Us'],
            ],
        ]);

        return [$user, $domain];
    }

    public function test_selecting_a_serp_competitor_tracks_it_and_queues_keyword_fetch(): void
    {
        Bus::fake([FetchCompetitorKeywords::class]);
        [$user, $domain] = $this->domainWithSerp();

        $this->actingAs($user)
            ->post(route('domains.competitors.store', $domain), ['competitor_domain' => 'rival.com', 'status' => 'tracked', 'source' => 'serp'])
            ->assertRedirect();

        $competitor = $domain->competitors()->sole();
        $this->assertSame('rival.com', $competitor->competitor_domain);
        $this->assertSame(DomainCompetitor::TRACKED, $competitor->status);
        $this->assertSame(DomainCompetitor::SOURCE_SERP, $competitor->source);
        $this->assertTrue($competitor->isFetchingKeywords());
        Bus::assertDispatched(FetchCompetitorKeywords::class, fn ($job) => $job->competitorId === $competitor->id);
    }

    public function test_manual_add_normalizes_url_and_rejects_own_domain(): void
    {
        Bus::fake([FetchCompetitorKeywords::class]);
        [$user, $domain] = $this->domainWithSerp();

        $this->actingAs($user)->post(route('domains.competitors.store', $domain), ['competitor_domain' => 'https://www.Other-Site.com/urunler']);
        $this->actingAs($user)->post(route('domains.competitors.store', $domain), ['competitor_domain' => 'www.example.com']);

        $this->assertSame(['other-site.com'], $domain->competitors()->pluck('competitor_domain')->all());
        $this->assertSame(DomainCompetitor::SOURCE_MANUAL, $domain->competitors()->sole()->source);
    }

    public function test_ignored_competitor_is_hidden_from_serp_discovery(): void
    {
        [$user, $domain] = $this->domainWithSerp();

        $this->actingAs($user)->post(route('domains.competitors.store', $domain), ['competitor_domain' => 'wikipedia.org', 'status' => 'ignored', 'source' => 'serp']);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));
        $response->assertOk();
        $response->assertSee('Rakip değil olarak işaretlenenler (1)');
        $this->assertSame(['rival.com'], array_column($response->viewData('competitorAnalysis')['competitors'], 'domain'));
    }

    public function test_read_only_member_cannot_change_competitors(): void
    {
        [, $domain] = $this->domainWithSerp();
        $member = User::factory()->create(['approved_at' => now()]);
        $domain->members()->create(['user_id' => $member->id, 'email' => $member->email, 'permission' => DomainMember::READ]);

        $this->actingAs($member)
            ->post(route('domains.competitors.store', $domain), ['competitor_domain' => 'rival.com'])
            ->assertForbidden();
    }

    public function test_gap_lists_untracked_keywords_shared_by_competitors_first(): void
    {
        [$user, $domain] = $this->domainWithSerp();
        $domain->update(['dismissed_keyword_suggestions' => ['reddedilen kelime']]);

        $domain->competitors()->create([
            'competitor_domain' => 'rival.com', 'status' => 'tracked', 'source' => 'serp',
            'keywords' => [
                ['keyword' => 'Koşu Ayakkabısı', 'position' => 1, 'search_volume' => 9000, 'url' => null],
                ['keyword' => 'maraton ayakkabısı', 'position' => 4, 'search_volume' => 500, 'url' => 'https://rival.com/maraton'],
                ['keyword' => 'trail ayakkabı', 'position' => 2, 'search_volume' => 3000, 'url' => null],
                ['keyword' => 'reddedilen kelime', 'position' => 1, 'search_volume' => 10, 'url' => null],
            ],
            'keywords_source' => 'dataforseo', 'keywords_fetched_at' => now(),
        ]);
        $domain->competitors()->create([
            'competitor_domain' => 'other.com', 'status' => 'tracked', 'source' => 'manual',
            'keywords' => [['keyword' => 'maraton ayakkabısı', 'position' => 2, 'search_volume' => 500, 'url' => null]],
            'keywords_source' => 'dataforseo', 'keywords_fetched_at' => now(),
        ]);
        // Ignored rakiplerin kelimeleri capraz kontrole girmez.
        $domain->competitors()->create([
            'competitor_domain' => 'wikipedia.org', 'status' => 'ignored', 'source' => 'serp',
            'keywords' => [['keyword' => 'ansiklopedi', 'position' => 1, 'search_volume' => 99999, 'url' => null]],
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));
        $response->assertOk();
        $gap = $response->viewData('competitorKeywordGap');

        $this->assertSame(['maraton ayakkabısı', 'trail ayakkabı'], array_column($gap, 'keyword'));
        $this->assertSame(2, $gap[0]['competitor_count']);
        $this->assertSame(2, $gap[0]['best_position']);
        $response->assertSee('Rakip Anahtar Kelime Boşlukları');
        $response->assertSee('trail ayakkabı');
    }

    public function test_fetch_job_stores_dataforseo_ranked_keywords(): void
    {
        [, $domain] = $this->domainWithSerp();
        $competitor = $domain->competitors()->create(['competitor_domain' => 'rival.com', 'status' => 'tracked', 'source' => 'serp', 'keywords_requested_at' => now()]);

        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode([
            'status_code' => 20000,
            'tasks' => [[
                'status_code' => 20000,
                'result' => [[
                    'items' => [
                        ['keyword_data' => ['keyword' => 'trail ayakkabı', 'keyword_info' => ['search_volume' => 3000]], 'ranked_serp_element' => ['serp_item' => ['rank_group' => 5, 'url' => 'https://rival.com/a']]],
                        ['keyword_data' => ['keyword' => 'trail ayakkabı', 'keyword_info' => ['search_volume' => 3000]], 'ranked_serp_element' => ['serp_item' => ['rank_group' => 2, 'url' => 'https://rival.com/b']]],
                    ],
                ]],
            ]],
        ]))]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $this->app->instance(RankedKeywordsFetcher::class, new RankedKeywordsFetcher(new DataForSeoClient(new Client(['handler' => $stack]), 'l', 'p')));

        (new FetchCompetitorKeywords($competitor->id))->handle(app(RankedKeywordsFetcher::class), app(CompetitorSiteCrawler::class));

        $competitor->refresh();
        $this->assertSame(DomainCompetitor::KEYWORDS_DATAFORSEO, $competitor->keywords_source);
        $this->assertSame([['keyword' => 'trail ayakkabı', 'position' => 2, 'search_volume' => 3000, 'url' => 'https://rival.com/b', 'score' => null]], $competitor->keywords);
        $this->assertFalse($competitor->isFetchingKeywords());
        $this->assertNull($competitor->keywords_error);

        $sent = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('rival.com', $sent[0]['target']);
    }

    public function test_fetch_job_falls_back_to_site_crawl_without_dataforseo(): void
    {
        [, $domain] = $this->domainWithSerp();
        $competitor = $domain->competitors()->create(['competitor_domain' => 'rival.com', 'status' => 'tracked', 'source' => 'manual']);

        $crawler = $this->createMock(CompetitorSiteCrawler::class);
        $crawler->expects($this->once())->method('crawl')->with('https://rival.com/')
            ->willReturn([['keyword' => 'koşu bandı', 'position' => null, 'search_volume' => null, 'url' => 'https://rival.com/', 'score' => 1.0]]);

        (new FetchCompetitorKeywords($competitor->id))->handle(app(RankedKeywordsFetcher::class), $crawler);

        $competitor->refresh();
        $this->assertSame(DomainCompetitor::KEYWORDS_CRAWL, $competitor->keywords_source);
        $this->assertSame('koşu bandı', $competitor->keywords[0]['keyword']);
    }

    public function test_mcp_set_competitor_and_gap_tools(): void
    {
        Bus::fake([FetchCompetitorKeywords::class]);
        [$user, $domain] = $this->domainWithSerp();
        Passport::actingAs($user, [McpScopes::READ, McpScopes::WRITE]);

        SeoCheckerServer::tool(SetCompetitor::class, ['domain' => 'example.com', 'competitor' => 'https://www.rival.com/'])
            ->assertOk()->assertSee('"status":"tracked"')->assertSee('"source":"serp"');
        SeoCheckerServer::tool(SetCompetitor::class, ['domain' => 'example.com', 'competitor' => 'wikipedia.org', 'status' => 'ignored'])
            ->assertOk()->assertSee('"status":"ignored"');
        SeoCheckerServer::tool(SetCompetitor::class, ['domain' => 'example.com', 'competitor' => 'example.com'])
            ->assertHasErrors();

        Bus::assertDispatchedTimes(FetchCompetitorKeywords::class, 1);

        $domain->competitors()->where('competitor_domain', 'rival.com')->update([
            'keywords' => json_encode([['keyword' => 'trail ayakkabı', 'position' => 3, 'search_volume' => 3000, 'url' => null]]),
            'keywords_fetched_at' => now()->addSecond(),
        ]);

        SeoCheckerServer::tool(GetCompetitorKeywordGap::class, ['domain' => 'example.com'])
            ->assertOk()->assertSee('"gap_count":1')->assertSee('trail ayakkab')->assertSee('"best_competitor_position":3');

        SeoCheckerServer::tool(GetCompetitors::class, ['domain' => 'example.com'])
            ->assertOk()->assertSee('"selected_competitors":["rival.com"]')->assertSee('"ignored_competitors":["wikipedia.org"]')->assertDontSee('"domain":"wikipedia.org"');
    }

    public function test_mcp_set_competitor_requires_write_access(): void
    {
        [, $domain] = $this->domainWithSerp();
        $member = User::factory()->create(['approved_at' => now()]);
        $domain->members()->create(['user_id' => $member->id, 'email' => $member->email, 'permission' => DomainMember::READ]);
        Passport::actingAs($member, [McpScopes::READ, McpScopes::WRITE]);

        SeoCheckerServer::tool(SetCompetitor::class, ['domain' => 'example.com', 'competitor' => 'rival.com'])->assertHasErrors();
        $this->assertSame(0, $domain->competitors()->count());
    }
}
