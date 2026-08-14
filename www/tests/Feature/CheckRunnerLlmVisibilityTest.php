<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use App\Services\CheckRunner;
use App\Services\Llm\AnthropicVisibilityChecker;
use App\Services\Llm\GeminiVisibilityChecker;
use App\Services\Llm\OpenAiVisibilityChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CheckRunner::buildLlmVisibility()'in kosullu sarmalama mantigini test
 * eder - gercek SERP/on-page/Lighthouse aglari bu sandbox ortaminda zaten
 * hizlica basarisiz oluyor (bkz. RunDomainSiteCheckTest'teki ayni gozlem),
 * bu yuzden yalnizca OpenAiVisibilityChecker'in Client'ini sahte yanitla
 * degistirip geri kalan zincirin dogal hata-yonetimine birakiyoruz.
 */
class CheckRunnerLlmVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SerpScraper/OnPageSeoAnalyzer/PageSpeedInsightsClient hepsi ayni
        // paylasilan Client::class singleton'ini kullaniyor - bunlarin
        // gercek aglara cikip yavaslamasini (ve testin internet erisimine
        // bagimli olmasini) onlemek icin genis bir bos-200 kuyrugu ile
        // degistiriyoruz. OpenAI checker'i kendi ayri client'ini kullaniyor
        // (bkz. fakeOpenAi()), bu yuzden bu degisiklikten etkilenmiyor.
        $responses = array_fill(0, 20, new Response(200, [], '{}'));
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    private function fakeOpenAi(string $responseText): void
    {
        $body = json_encode(['choices' => [['message' => ['content' => $responseText]]]]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $body)]))]);

        $this->app->instance(OpenAiVisibilityChecker::class, new OpenAiVisibilityChecker($client, 'gpt-4.1-nano'));
    }

    public function test_llm_visibility_is_null_when_domain_has_it_disabled(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'llm_visibility_enabled' => false]);
        $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-test']);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $this->fakeOpenAi('example.com burada geçebilir ama önemi yok');

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertNull($check->llm_visibility);
    }

    public function test_llm_visibility_is_null_when_enabled_but_no_keys_configured(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'llm_visibility_enabled' => true]);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertNull($check->llm_visibility);
    }

    public function test_only_configured_providers_are_included(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'llm_visibility_enabled' => true]);
        $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-test']);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $this->fakeOpenAi('Bunun için example.com adresine bakabilirsin.');

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertArrayHasKey('openai', $check->llm_visibility);
        $this->assertArrayNotHasKey('anthropic', $check->llm_visibility);
        $this->assertArrayNotHasKey('gemini', $check->llm_visibility);
    }

    public function test_openai_mention_result_is_stored_on_the_check(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'llm_visibility_enabled' => true]);
        $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'musteri', 'api_key' => 'sk-test']);
        $keyword = $domain->keywords()->create(['keyword' => 'örnek kelime']);

        $this->fakeOpenAi('Bunun için example.com adresine bakabilirsin.');

        $check = app(CheckRunner::class)->run($keyword);

        $this->assertTrue($check->llm_visibility['openai']['present']);
        $this->assertStringContainsString('example.com', $check->llm_visibility['openai']['response']);
        $this->assertNull($check->llm_visibility['openai']['error']);
        $this->assertStringContainsString('örnek kelime', $check->llm_visibility['openai']['prompt']);
    }
}
