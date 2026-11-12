<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Models\Domain;
use App\Models\Keyword;
use InvalidArgumentException;
use Throwable;

/**
 * Prompt Explorer icin "soru oner" sihirbazi: secilen anahtar kelime(ler)
 * ve arama niyetinden, gercek bir musterinin bir yapay zeka asistanina
 * soracagi dogal soru varyasyonlari uretir. Domain'in kendi API
 * anahtarlarindan biriyle TEK istek atar (Prompt Explorer'in kendisi gibi
 * maliyet domain sahibine aittir).
 *
 * Sorular bilerek marka/domain adi icermez: amac, asistanin siteyi
 * kendiliginden onerip onermedigini olcmek (bkz. LlmVisibilityPrompt).
 */
final class PromptSuggester
{
    public const DEFAULT_COUNT = 8;
    public const MAX_COUNT = 15;

    public const INTENTS = ['informational', 'commercial', 'transactional', 'navigational', 'local'];

    /** Soru uretimi icin tercih sirasi (Perplexity web aramasi yaptigi icin en sonda). */
    private const PROVIDER_PREFERENCE = ['anthropic', 'openai', 'gemini', 'perplexity'];

    private const MIN_LENGTH = 8;

    /**
     * @param  list<Keyword>  $keywords  En az bir anahtar kelime.
     * @return array{provider: string, suggestions: list<string>}
     *
     * @throws InvalidArgumentException API anahtari yoksa ya da saglayici hata dondururse.
     */
    public function suggest(Domain $domain, array $keywords, ?string $intent = null, int $count = self::DEFAULT_COUNT): array
    {
        if ($keywords === []) {
            throw new InvalidArgumentException(__('Soru önermek için en az bir anahtar kelime seçin.'));
        }

        $apiKeys = $domain->llmApiKeys->keyBy('provider');
        $provider = collect(self::PROVIDER_PREFERENCE)->first(fn (string $p) => isset($apiKeys[$p]));
        if ($provider === null) {
            throw new InvalidArgumentException(__('Soru önermek için en az bir sağlayıcının API anahtarı gerekiyor.'));
        }

        $count = max(1, min(self::MAX_COUNT, $count));
        $intent = in_array($intent, self::INTENTS, true) ? $intent : null;

        try {
            $result = $this->checker($provider)->ask(
                $this->instruction($keywords, $intent, $count),
                $domain->domain,
                $apiKeys[$provider]->api_key,
            );
        } catch (Throwable $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }

        if ($result->error !== null || $result->response === null) {
            throw new InvalidArgumentException($result->error ?? __('Sağlayıcı boş yanıt döndü.'));
        }

        $suggestions = $this->parse($result->response, $count, $domain->domain);
        if ($suggestions === []) {
            throw new InvalidArgumentException(__('Sağlayıcının cevabından soru çıkarılamadı, tekrar deneyin.'));
        }

        return ['provider' => $provider, 'suggestions' => $suggestions];
    }

    /**
     * @param  list<Keyword>  $keywords
     */
    public function instruction(array $keywords, ?string $intent, int $count): string
    {
        $lines = [];
        foreach ($keywords as $keyword) {
            $detectedIntent = $keyword->search_intent ? " (search intent: {$keyword->search_intent})" : '';
            $lines[] = '- ' . $keyword->keyword . $detectedIntent;
        }

        $intentLine = $intent !== null
            ? "Focus on the \"{$intent}\" search intent."
            : 'Mix search intents: informational, commercial (comparing options), transactional (ready to buy/book) and, if it fits, local.';

        return implode("\n", [
            "Write {$count} different questions that a real customer might type into an AI assistant such as ChatGPT when looking for what these search keywords describe:",
            ...$lines,
            '',
            $intentLine,
            'Rules:',
            '- Write the questions in the same language as the keywords.',
            '- Sound like a person, not a search query: natural, specific, sometimes with context (budget, location, situation).',
            '- Questions should make the assistant recommend specific businesses, products, brands or websites.',
            '- Do not mention any brand, company or website name.',
            '- Vary the wording and angle; no near-duplicates.',
            '- Output only a JSON array of strings, nothing else.',
        ]);
    }

    /**
     * Cevaptan sorulari cikarir: once JSON dizi, olmazsa satir satir
     * (numara/madde isareti/tirnak temizlenerek).
     *
     * @return list<string>
     */
    public function parse(string $response, int $count, string $domain): array
    {
        $candidates = [];

        if (preg_match('/\[[\s\S]*\]/u', $response, $m) === 1) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                $candidates = array_filter($decoded, 'is_string');
            }
        }

        if ($candidates === []) {
            $candidates = preg_split('/\R/u', $response) ?: [];
        }

        $own = mb_strtolower(LlmMentions::normalizeHost($domain));
        $seen = [];
        $result = [];

        foreach ($candidates as $candidate) {
            $text = trim((string) preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', (string) $candidate));
            $text = trim($text, " \t\"'“”„«»`");

            $key = mb_strtolower($text);
            if (mb_strlen($text) < self::MIN_LENGTH || mb_strlen($text) > PromptExplorer::MAX_PROMPT_LENGTH
                || isset($seen[$key]) || ($own !== '' && str_contains($key, $own))) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $text;

            if (count($result) >= $count) {
                break;
            }
        }

        return $result;
    }

    private function checker(string $provider): object
    {
        return match ($provider) {
            'openai' => app(OpenAiVisibilityChecker::class),
            'anthropic' => app(AnthropicVisibilityChecker::class),
            'gemini' => app(GeminiVisibilityChecker::class),
            'perplexity' => app(PerplexityVisibilityChecker::class),
        };
    }
}
