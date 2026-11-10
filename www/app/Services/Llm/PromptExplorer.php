<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Jobs\RunPromptExploration;
use App\Models\Domain;
use App\Models\DomainLlmApiKey;
use App\Models\PromptExploration;
use App\Models\User;
use InvalidArgumentException;

/**
 * Prompt Explorer'in arayuz ve MCP tarafindan ortak kullanilan giris noktasi.
 */
final class PromptExplorer
{
    public const MAX_PROMPT_LENGTH = 2000;

    /**
     * @return list<string> domain'de API key'i olan saglayicilar (sabit sirada)
     */
    public function availableProviders(Domain $domain): array
    {
        $configured = $domain->llmApiKeys->pluck('provider')->all();

        return array_values(array_filter(DomainLlmApiKey::PROVIDERS, fn ($p) => in_array($p, $configured, true)));
    }

    /**
     * @param  list<string>|null  $providers  null: anahtari olan hepsi
     * @param  bool  $sync  true: cevaplar gelene kadar bekle (MCP); false: kuyruga at (arayuz)
     */
    public function start(Domain $domain, User $user, string $prompt, ?array $providers = null, bool $sync = false): PromptExploration
    {
        $prompt = trim($prompt);
        if ($prompt === '' || mb_strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            throw new InvalidArgumentException(__('Prompt 1-:max karakter olmalı.', ['max' => self::MAX_PROMPT_LENGTH]));
        }

        $available = $this->availableProviders($domain);
        $providers = $providers === null ? $available : array_values(array_intersect($available, $providers));

        if ($providers === []) {
            throw new InvalidArgumentException(__('Bu domain için yapay zekâ API anahtarı girilmemiş.'));
        }

        $exploration = $domain->promptExplorations()->create([
            'user_id' => $user->id,
            'prompt' => $prompt,
            'providers' => $providers,
            'results' => [],
        ]);

        if ($sync) {
            RunPromptExploration::dispatchSync($exploration->id);

            return $exploration->refresh();
        }

        RunPromptExploration::dispatch($exploration->id);

        return $exploration;
    }
}
