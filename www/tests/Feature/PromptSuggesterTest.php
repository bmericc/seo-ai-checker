<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use App\Services\Llm\AnthropicVisibilityChecker;
use App\Services\Llm\PromptSuggester;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptSuggesterTest extends TestCase
{
    use RefreshDatabase;

    private array $requests = [];

    private function fakeAnthropic(string $text, int $status = 200): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, [], json_encode($status === 200
                ? ['content' => [['type' => 'text', 'text' => $text]]]
                : ['error' => ['message' => $text]])),
        ]));
        $stack->push(Middleware::history($this->requests));
        $this->app->instance(AnthropicVisibilityChecker::class, new AnthropicVisibilityChecker(new Client(['handler' => $stack])));
    }

    private function setUpDomain(bool $admin = true): array
    {
        $user = User::factory()->create(['approved_at' => now(), 'is_admin' => $admin]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->llmApiKeys()->create(['provider' => 'anthropic', 'api_key' => 'sk-test', 'label' => 'bireysel']);
        $keyword = $domain->keywords()->create(['keyword' => 'koşu ayakkabısı', 'search_intent' => 'commercial']);

        return [$user, $domain, $keyword];
    }

    public function test_admin_gets_parsed_brand_free_suggestions(): void
    {
        [$user, $domain, $keyword] = $this->setUpDomain();
        $this->fakeAnthropic("İşte sorular:\n[\"Yeni başlayanlar için hangi koşu ayakkabısı iyi?\", \"example.com güvenilir mi?\", \"Yeni başlayanlar için hangi koşu ayakkabısı iyi?\", \"1000 TL altı koşu ayakkabısı önerir misin?\"]");

        $response = $this->actingAs($user)->postJson(route('domains.prompts.suggest', $domain), [
            'keyword_ids' => [$keyword->id],
            'intent' => 'transactional',
        ]);

        $response->assertOk()->assertJson([
            'provider' => 'Claude',
            'suggestions' => ['Yeni başlayanlar için hangi koşu ayakkabısı iyi?', '1000 TL altı koşu ayakkabısı önerir misin?'],
        ]);

        $body = json_decode((string) $this->requests[0]['request']->getBody(), true);
        $instruction = $body['messages'][0]['content'];
        $this->assertStringContainsString('koşu ayakkabısı (search intent: commercial)', $instruction);
        $this->assertStringContainsString('"transactional"', $instruction);
        $this->assertStringNotContainsString('example.com', $instruction);
    }

    public function test_provider_error_is_returned_as_422(): void
    {
        [$user, $domain, $keyword] = $this->setUpDomain();
        $this->fakeAnthropic('invalid x-api-key', 401);

        $this->actingAs($user)->postJson(route('domains.prompts.suggest', $domain), ['keyword_ids' => [$keyword->id]])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Anthropic HTTP 401: invalid x-api-key']);
    }

    public function test_non_admin_cannot_use_wizard(): void
    {
        [$user, $domain, $keyword] = $this->setUpDomain(admin: false);

        $this->actingAs($user)->postJson(route('domains.prompts.suggest', $domain), ['keyword_ids' => [$keyword->id]])->assertForbidden();
    }

    public function test_parse_falls_back_to_numbered_lines(): void
    {
        $parsed = (new PromptSuggester())->parse("1. Hangi koşu ayakkabısı dayanıklı?\n2) \"Maraton için ne önerirsin?\"\n- kısa\n", 5, 'example.com');

        $this->assertSame(['Hangi koşu ayakkabısı dayanıklı?', 'Maraton için ne önerirsin?'], $parsed);
    }
}
