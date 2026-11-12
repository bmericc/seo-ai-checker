<?php

namespace Tests\Feature;

use App\Console\Commands\CollectQueuedSerpResults;
use App\Jobs\FetchKeywordMetrics;
use App\Jobs\FetchLlmMentions;
use App\Jobs\RunKeywordCheck;
use App\Models\Domain;
use App\Models\DomainFact;
use App\Models\QueuedSerpTask;
use App\Models\User;
use App\Services\CheckRunner;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\KeywordMetricsFetcher;
use App\Services\DataForSeo\KeywordMetricsUpdater;
use App\Services\DataForSeo\LlmMentionsChecker;
use App\Services\DataForSeo\LlmScraperChecker;
use App\Services\Serp\DataForSeoSerpScraper;
use App\Services\Serp\SerpScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\Unit\DataForSeo\FakeDataForSeo;

class DataForSeoIntegrationTest extends TestCase
{
    use FakeDataForSeo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        // On-page/Lighthouse paylasilan Client'i kullanir; gercek aga cikmasin.
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler(array_fill(0, 20, new Response(200, [], '{}'))))]));
    }

    private function serpEnvelope(array $items, int $taskStatus = 20000): array
    {
        return ['status_code' => 20000, 'tasks' => [['status_code' => $taskStatus, 'result' => [['items' => $items]]]]];
    }

    /**
     * @param  list<Response>  $responses
     */
    private function fakeSerp(array $responses): DataForSeoSerpScraper
    {
        $scraper = new DataForSeoSerpScraper(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]), 'login', 'password');
        $this->app->instance(SerpScraper::class, $scraper);

        return $scraper;
    }

    private function domainWithKeyword(array $domainAttributes = []): array
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id] + $domainAttributes);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        return [$user, $domain, $keyword];
    }

    private function organic(int $position): array
    {
        return ['type' => 'organic', 'rank_absolute' => $position, 'url' => 'https://example.com/x', 'domain' => 'example.com', 'title' => 'X'];
    }

    public function test_check_records_desktop_and_mobile_positions(): void
    {
        [, , $keyword] = $this->domainWithKeyword();
        $this->fakeSerp([
            new Response(200, [], json_encode($this->serpEnvelope([$this->organic(4)]))),
            new Response(200, [], json_encode($this->serpEnvelope([$this->organic(7)]))),
        ]);

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertSame(4, $check->target_position);
        $this->assertSame(7, $check->mobile_target_position);
        $this->assertFalse($check->mobile_blocked);
        $this->assertSame('manual', $check->source);
    }

    public function test_direct_scraping_skips_mobile(): void
    {
        [, , $keyword] = $this->domainWithKeyword();

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertNull($check->mobile_blocked);
        $this->assertNull($check->mobile_target_position);
    }

    public function test_llm_scraper_runs_without_api_keys_when_enabled(): void
    {
        [, , $keyword] = $this->domainWithKeyword(['llm_visibility_enabled' => true, 'llm_scraper_enabled' => true]);
        $this->fakeSerp(array_fill(0, 2, new Response(200, [], json_encode($this->serpEnvelope([])))));
        $this->app->instance(LlmScraperChecker::class, new LlmScraperChecker($this->fakeClient([
            $this->taskResponse(['markdown' => 'example.com iyi bir kaynak', 'sources' => [['url' => 'https://example.com/a']]]),
            $this->taskResponse(['markdown' => 'other.org', 'sources' => []]),
        ])));

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertSame(['chatgpt_web', 'gemini_web'], array_keys($check->aiCheck?->results));
        $this->assertTrue($check->aiCheck?->results['chatgpt_web']['present']);
        $this->assertSame(['https://example.com/a'], $check->aiCheck?->results['chatgpt_web']['citations']);
        $this->assertFalse($check->aiCheck?->results['gemini_web']['present']);
    }

    public function test_llm_scraper_requires_master_toggle(): void
    {
        [, , $keyword] = $this->domainWithKeyword(['llm_visibility_enabled' => false, 'llm_scraper_enabled' => true]);
        $this->app->instance(LlmScraperChecker::class, new LlmScraperChecker($this->fakeClient([])));

        $this->assertNull(app(CheckRunner::class)->run($keyword)->aiCheck);
    }

    public function test_metrics_updater_writes_and_skips_fresh(): void
    {
        [, , $keyword] = $this->domainWithKeyword();
        $updater = new KeywordMetricsUpdater(new KeywordMetricsFetcher($this->fakeClient([
            $this->taskResponse(['items' => [[
                'keyword' => 'örnek kelime',
                'keyword_info' => ['search_volume' => 880, 'cpc' => 1.2],
                'keyword_properties' => ['keyword_difficulty' => 55],
                'search_intent_info' => ['main_intent' => 'commercial'],
            ]]]),
        ])));

        $this->assertSame(1, $updater->refresh(collect([$keyword])));
        $keyword->refresh();
        $this->assertSame(880, $keyword->search_volume);
        $this->assertSame(55, $keyword->keyword_difficulty);
        $this->assertSame('commercial', $keyword->search_intent);
        $this->assertNotNull($keyword->metrics_fetched_at);

        // Taze metrik tekrar sorgulanmaz (sahte client'ta baska yanit yok).
        $this->assertSame(0, $updater->refresh(collect([$keyword])));
    }

    public function test_metrics_updater_stores_error(): void
    {
        [, , $keyword] = $this->domainWithKeyword();
        $updater = new KeywordMetricsUpdater(new KeywordMetricsFetcher($this->fakeClient([
            new Response(200, [], json_encode(['status_code' => 40200, 'status_message' => 'Payment Required.'])),
        ])));

        Log::spy();

        $updater->refresh(collect([$keyword]));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => str_contains($context['error'], 'Payment Required.') && $context['keyword_count'] === 1,
        );
        $this->assertStringContainsString('Payment Required.', $keyword->refresh()->metrics_error);
        $this->assertNull($keyword->metrics_fetched_at);
    }

    public function test_new_keyword_queues_metrics_only_when_configured(): void
    {
        Bus::fake([FetchKeywordMetrics::class]);
        [, $domain] = $this->domainWithKeyword();
        Bus::assertNotDispatched(FetchKeywordMetrics::class);

        config(['seo.dataforseo.login' => 'l', 'seo.dataforseo.password' => 'p']);
        $kw = $domain->keywords()->create(['keyword' => 'ikinci']);

        Bus::assertDispatched(FetchKeywordMetrics::class, fn ($job) => $job->keywordIds === [$kw->id]);
    }

    public function test_weekly_command_queues_serp_tasks_and_collector_dispatches_checks(): void
    {
        Bus::fake([RunKeywordCheck::class, FetchKeywordMetrics::class]);
        [, , $keyword] = $this->domainWithKeyword();

        $this->fakeSerp([
            // task_post: desktop + mobile gorevleri (tag = QueuedSerpTask id)
            new Response(200, [], json_encode(['status_code' => 20000, 'tasks' => [
                ['id' => 't-desktop', 'status_code' => 20100, 'data' => ['tag' => '1']],
                ['id' => 't-mobile', 'status_code' => 20100, 'data' => ['tag' => '2']],
            ]])),
        ]);

        $this->artisan('keywords:run-weekly-checks')->assertSuccessful();

        $tasks = QueuedSerpTask::query()->orderBy('id')->get();
        $this->assertSame(['t-desktop', 't-mobile'], $tasks->pluck('task_id')->all());
        $this->assertSame(['desktop', 'mobile'], $tasks->pluck('device')->all());

        // Ilk toplama: desktop hazir, mobil kuyrukta -> henuz kontrol yok.
        $this->fakeSerp([
            new Response(200, [], json_encode($this->serpEnvelope([$this->organic(2)]))),
            new Response(200, [], json_encode(['status_code' => 20000, 'tasks' => [['status_code' => 40601]]])),
        ]);
        $this->artisan('keywords:collect-queued-serp')->assertSuccessful();
        Bus::assertNotDispatched(RunKeywordCheck::class);

        // Ikinci toplama: mobil de hazir -> kontrol job'i bir kez kuyruga girer.
        $this->fakeSerp([new Response(200, [], json_encode($this->serpEnvelope([$this->organic(5)])))]);
        $this->artisan('keywords:collect-queued-serp')->assertSuccessful();
        $this->artisan('keywords:collect-queued-serp')->assertSuccessful();

        Bus::assertDispatchedTimes(RunKeywordCheck::class, 1);
        Bus::assertDispatched(RunKeywordCheck::class, fn ($job) => $job->keywordId === $keyword->id && $job->scheduled && $job->serpBatch === $tasks[0]->batch);

        // Job, kuyruk sonuclarini live SERP'e gitmeden kullanir; LLM cagrilmaz.
        $this->fakeSerp([]);
        $job = new RunKeywordCheck($keyword->id, scheduled: true, serpBatch: $tasks[0]->batch);
        $job->handle(app(CheckRunner::class));

        $check = $keyword->checks()->first();
        $this->assertSame(2, $check->target_position);
        $this->assertSame(5, $check->mobile_target_position);
        $this->assertSame('scheduled', $check->source);
        $this->assertNull($check->aiCheck?->results);
    }

    public function test_collector_times_out_stuck_tasks(): void
    {
        Bus::fake([RunKeywordCheck::class]);
        [, , $keyword] = $this->domainWithKeyword();
        $task = QueuedSerpTask::query()->create(['keyword_id' => $keyword->id, 'batch' => 'b', 'device' => 'desktop', 'task_id' => 't']);
        $task->forceFill(['created_at' => now()->subHours(7)])->save();

        $this->fakeSerp([new Response(200, [], json_encode(['status_code' => 20000, 'tasks' => [['status_code' => 40602]]]))]);
        $this->artisan('keywords:collect-queued-serp')->assertSuccessful();

        $this->assertNotNull($task->refresh()->error);
        Bus::assertDispatched(RunKeywordCheck::class);
    }

    public function test_llm_mentions_job_stores_shared_fact(): void
    {
        [, $domain] = $this->domainWithKeyword();
        $this->app->instance(LlmMentionsChecker::class, new LlmMentionsChecker($this->fakeClient([
            $this->taskResponse(['total' => ['platform' => [['mentions' => 3]]]]),
            $this->taskResponse(['items' => []]),
            $this->taskResponse(['total' => ['platform' => []]]),
            $this->taskResponse(['items' => []]),
        ])));

        (new FetchLlmMentions($domain->id))->handle(app(LlmMentionsChecker::class));

        $fact = DomainFact::query()->where('domain', 'example.com')->first();
        $this->assertSame(3, $fact->llm_mentions['platforms']['google']['mentions']);
        $this->assertNotNull($fact->llm_mentions_checked_at);
    }

    public function test_domain_page_renders_metrics_mentions_and_toggles(): void
    {
        [$user, $domain, $keyword] = $this->domainWithKeyword(['llm_visibility_enabled' => true]);
        $keyword->update(['search_volume' => 12100, 'keyword_difficulty' => 42, 'search_intent' => 'informational', 'metrics_fetched_at' => now()]);
        DomainFact::forDomain('example.com')->update([
            'llm_mentions' => ['platforms' => ['google' => ['location_code' => 2792, 'language_code' => 'tr', 'mentions' => 77, 'ai_search_volume' => 10, 'top_questions' => [['question' => 'en iyi örnek sitesi', 'ai_search_volume' => 5, 'cited' => true, 'sources_count' => 1]], 'error' => null]]],
            'llm_mentions_checked_at' => now(),
        ]);

        $this->actingAs($user)->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee('12.100')
            ->assertSee('AI Mention Verisi')
            ->assertSee('en iyi örnek sitesi')
            ->assertSee('ChatGPT ve Gemini web arayüzü');

        $this->actingAs($user)->get(route('keywords.show', $keyword))
            ->assertOk()
            ->assertSee('Anahtar Kelime Metrikleri')
            ->assertSee('bilgi');
    }

    public function test_scraper_toggle_is_admin_only_and_requires_dataforseo(): void
    {
        [$admin, $domain] = $this->domainWithKeyword();
        $owner = User::factory()->create(['approved_at' => now()]);
        $domain->update(['user_id' => $owner->id]);

        $this->actingAs($owner)->patch(route('domains.llm-scraper.update', $domain), ['llm_scraper_enabled' => 1])->assertForbidden();

        $this->actingAs($admin)->patch(route('domains.llm-scraper.update', $domain), ['llm_scraper_enabled' => 1]);
        $this->assertFalse($domain->refresh()->llm_scraper_enabled);

        config(['seo.dataforseo.login' => 'l', 'seo.dataforseo.password' => 'p']);
        $this->app->forgetInstance(DataForSeoClient::class);
        $this->actingAs($admin)->patch(route('domains.llm-scraper.update', $domain), ['llm_scraper_enabled' => 1]);
        $this->assertTrue($domain->refresh()->llm_scraper_enabled);
    }

    public function test_refresh_metrics_route_dispatches_for_owner(): void
    {
        Bus::fake([FetchKeywordMetrics::class]);
        [, $domain, $keyword] = $this->domainWithKeyword();
        $owner = User::factory()->create(['approved_at' => now()]);
        $domain->update(['user_id' => $owner->id]);
        config(['seo.dataforseo.login' => 'l', 'seo.dataforseo.password' => 'p']);
        $this->app->forgetInstance(DataForSeoClient::class);

        $this->actingAs($owner)->post(route('domains.keyword-metrics.refresh', $domain))->assertRedirect();

        Bus::assertDispatched(FetchKeywordMetrics::class, fn ($job) => $job->keywordIds === [$keyword->id]);
    }
}
