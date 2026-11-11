<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DomainLlmApiKey;

/**
 * Manuel (MCP) kontrollerde cevabi yazan asistanin adi. API'li dort
 * saglayici (openai/anthropic/gemini/perplexity) ayni anahtarla saklanir ki
 * gecmiste API sonuclariyla yan yana dursun; Grok, DeepSeek gibi digerleri
 * kendi adlariyla kaydedilir.
 */
final class AiAssistant
{
    /** Sik kullanilan takma adlar => saklanan anahtar. */
    private const ALIASES = [
        'chatgpt' => 'openai',
        'gpt' => 'openai',
        'claude' => 'anthropic',
        'google' => 'gemini',
        'bard' => 'gemini',
        'xai' => 'grok',
        'microsoft-copilot' => 'copilot',
        'meta' => 'meta-ai',
    ];

    private const LABELS = [
        'grok' => 'Grok',
        'deepseek' => 'DeepSeek',
        'copilot' => 'Copilot',
        'mistral' => 'Mistral',
        'meta-ai' => 'Meta AI',
        'llama' => 'Llama',
        'qwen' => 'Qwen',
        'kimi' => 'Kimi',
        'cursor' => 'Cursor',
        'other' => 'Diğer',
    ];

    /** Gecerli anahtar bicimi: kucuk harf, rakam, tire. */
    public const PATTERN = '/^[a-z0-9][a-z0-9-]{0,39}$/';

    public static function normalize(string $name): ?string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($name))), '-');
        $slug = self::ALIASES[$slug] ?? $slug;

        return preg_match(self::PATTERN, $slug) === 1 ? $slug : null;
    }

    public static function label(string $key): string
    {
        $label = DomainLlmApiKey::PROVIDER_LABELS[$key] ?? self::LABELS[$key] ?? null;

        return $label !== null ? __($label) : ucwords(str_replace('-', ' ', $key));
    }
}
