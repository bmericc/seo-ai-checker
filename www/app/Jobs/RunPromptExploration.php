<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PromptExploration;
use App\Services\Llm\AnthropicVisibilityChecker;
use App\Services\Llm\GeminiVisibilityChecker;
use App\Services\Llm\LlmVisibilityResult;
use App\Services\Llm\OpenAiVisibilityChecker;
use App\Services\Llm\PerplexityVisibilityChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Prompt Explorer'daki bir prompt'u secilen saglayicilara sirayla sorar.
 * Her saglayici bittiginde sonuc hemen kaydedilir; sayfa kendini yenileyerek
 * cevaplari geldikce gosterir.
 */
class RunPromptExploration implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly int $explorationId)
    {
    }

    public function handle(): void
    {
        $exploration = PromptExploration::query()->with('domain.llmApiKeys')->find($this->explorationId);
        if ($exploration === null || $exploration->isComplete()) {
            return;
        }

        $apiKeys = $exploration->domain->llmApiKeys->keyBy('provider');
        $results = $exploration->results ?? [];

        foreach ($exploration->providers as $provider) {
            if (isset($results[$provider])) {
                continue;
            }

            $apiKey = $apiKeys[$provider]->api_key ?? null;
            $results[$provider] = $apiKey === null
                ? ['present' => false, 'response' => null, 'error' => __('Bu sağlayıcı için API anahtarı yok.'), 'model' => null]
                : $this->ask($provider, $exploration->prompt, $exploration->domain->domain, $apiKey);

            $exploration->update(['results' => $results]);
        }

        $exploration->update(['completed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        PromptExploration::query()->whereKey($this->explorationId)->update(['completed_at' => now()]);
    }

    private function ask(string $provider, string $prompt, string $domain, string $apiKey): array
    {
        $checker = match ($provider) {
            'openai' => app(OpenAiVisibilityChecker::class),
            'anthropic' => app(AnthropicVisibilityChecker::class),
            'gemini' => app(GeminiVisibilityChecker::class),
            'perplexity' => app(PerplexityVisibilityChecker::class),
            default => null,
        };

        if ($checker === null) {
            return ['present' => false, 'response' => null, 'error' => __('Bilinmeyen sağlayıcı.'), 'model' => null];
        }

        try {
            /** @var LlmVisibilityResult $result */
            $result = $checker->ask($prompt, $domain, $apiKey);

            return $result->toArray();
        } catch (Throwable $e) {
            return ['present' => false, 'response' => null, 'error' => $e->getMessage(), 'model' => null];
        }
    }
}
