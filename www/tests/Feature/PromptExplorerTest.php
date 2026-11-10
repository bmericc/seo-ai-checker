<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        // Docker ortaminda QUEUE_CONNECTION=database gercek env var oldugu icin
        // phpunit.xml'deki deger uygulanmiyor; job'in testte calismasi icin.
        config(['queue.default' => 'sync']);
    }

    private function fakeHttp(array $responses): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $this->app->instance(Client::class, $client);
        // Checker'lar singleton; yeni Client'i kullanmalari icin yeniden olusturulur.
        foreach ([\App\Services\Llm\OpenAiVisibilityChecker::class, \App\Services\Llm\PerplexityVisibilityChecker::class] as $class) {
            $this->app->forgetInstance($class);
        }
    }

    private function adminWithDomain(): array
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);
        $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-test']);
        $domain->llmApiKeys()->create(['provider' => 'perplexity', 'label' => 'bireysel', 'api_key' => 'pplx-test']);

        return [$admin, $domain];
    }

    public function test_admin_runs_a_prompt_across_configured_providers(): void
    {
        [$admin, $domain] = $this->adminWithDomain();
        $this->fakeHttp([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Try **example.com** or rival.com.']]]])),
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Top picks: rival.com and other.org [1]']]],
                'citations' => ['https://www.example.com/page', 'https://rival.com/'],
            ])),
        ]);

        $response = $this->actingAs($admin)->post(route('domains.prompts.store', $domain), ['prompt' => 'best widgets?']);

        $exploration = $domain->promptExplorations()->first();
        $response->assertRedirect(route('domains.prompts.show', [$domain, $exploration]));
        $this->assertTrue($exploration->isComplete());
        $this->assertSame(['openai', 'perplexity'], $exploration->providers);
        $this->assertTrue($exploration->results['openai']['present']);
        // Perplexity: metinde gecmiyor ama kaynaklarda var.
        $this->assertTrue($exploration->results['perplexity']['present']);
        $this->assertSame(['https://www.example.com/page', 'https://rival.com/'], $exploration->results['perplexity']['citations']);
        $this->assertSame(['mentioned' => 2, 'answered' => 2], $exploration->mentionSummary());
        $this->assertSame('rival.com', array_key_first($exploration->mentionedSites()));
        $this->assertArrayNotHasKey('example.com', $exploration->mentionedSites());

        $this->actingAs($admin)->get(route('domains.prompts.show', [$domain, $exploration]))
            ->assertOk()
            ->assertSee('<mark>example.com</mark>', false)
            ->assertSee('Perplexity')
            ->assertSee('rival.com');
    }

    public function test_prompt_is_sent_without_system_instruction(): void
    {
        [$admin, $domain] = $this->adminWithDomain();
        $domain->llmApiKeys()->where('provider', 'perplexity')->delete();
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'none']]]])),
        ]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
        $this->app->forgetInstance(\App\Services\Llm\OpenAiVisibilityChecker::class);

        $this->actingAs($admin)->post(route('domains.prompts.store', $domain), ['prompt' => 'raw question']);

        $sent = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame([['role' => 'user', 'content' => 'raw question']], $sent['messages']);
        $this->assertCount(1, $history);
    }

    public function test_provider_errors_are_kept_per_provider(): void
    {
        [$admin, $domain] = $this->adminWithDomain();
        $this->fakeHttp([
            new Response(401, [], json_encode(['error' => ['message' => 'bad key']])),
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'example.com']]]])),
        ]);

        $this->actingAs($admin)->post(route('domains.prompts.store', $domain), ['prompt' => 'q']);

        $exploration = $domain->promptExplorations()->first();
        $this->assertStringContainsString('bad key', $exploration->results['openai']['error']);
        $this->assertSame(['mentioned' => 1, 'answered' => 1], $exploration->mentionSummary());
    }

    public function test_domain_without_api_keys_cannot_start(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'is_admin' => true]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $admin->id]);

        $this->actingAs($admin)->post(route('domains.prompts.store', $domain), ['prompt' => 'q'])->assertRedirect();

        $this->assertSame(0, $domain->promptExplorations()->count());
    }

    public function test_non_admin_owner_cannot_use_prompt_explorer(): void
    {
        $owner = User::factory()->create(['approved_at' => now(), 'is_admin' => false]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);
        $domain->llmApiKeys()->create(['provider' => 'openai', 'label' => 'bireysel', 'api_key' => 'sk-test']);

        $this->actingAs($owner)->post(route('domains.prompts.store', $domain), ['prompt' => 'q'])->assertForbidden();
    }
}
