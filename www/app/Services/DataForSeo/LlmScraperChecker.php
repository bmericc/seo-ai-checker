<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

use App\Services\Llm\LlmMentions;
use App\Services\Llm\LlmVisibilityPrompt;
use App\Services\Llm\LlmVisibilityResult;

/**
 * ChatGPT ve Gemini'nin GERCEK web arayuzunun cevabini (web aramasi ve
 * kaynak linkleriyle birlikte) DataForSEO LLM Scraper uzerinden alir.
 * https://docs.dataforseo.com/v3/ai_optimization/chat_gpt/llm_scraper/live/advanced/
 *
 * OpenAiVisibilityChecker/GeminiVisibilityChecker dogrudan model API'sini
 * cagirir; API cevabi kullanicinin chatgpt.com'da gordugu cevaptan farklidir
 * (web aramasi yok, kaynak yok). Bu sinif o farki kapatir - kullanici
 * domain icin kendi API key'ini girmez, maliyet uygulamanin DataForSEO
 * hesabindan cikar.
 */
final class LlmScraperChecker
{
    public const ENGINE_CHATGPT = 'chatgpt_web';
    public const ENGINE_GEMINI = 'gemini_web';

    /** @var array<string, string> sonuc anahtari => DataForSEO yolu */
    private const ENDPOINTS = [
        self::ENGINE_CHATGPT => '/v3/ai_optimization/chat_gpt/llm_scraper/live/advanced',
        self::ENGINE_GEMINI => '/v3/ai_optimization/gemini/llm_scraper/live/advanced',
    ];

    /** @var array<string, string> */
    private const MODEL_LABELS = [
        self::ENGINE_CHATGPT => 'chatgpt.com',
        self::ENGINE_GEMINI => 'gemini.google.com',
    ];

    public function __construct(
        private readonly DataForSeoClient $client,
        private readonly int $locationCode = 2792,
        private readonly string $languageCode = 'tr',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return list<string>
     */
    public static function engines(): array
    {
        return array_keys(self::ENDPOINTS);
    }

    public function check(string $engine, string $keyword, string $domain): LlmVisibilityResult
    {
        $prompt = LlmVisibilityPrompt::userPrompt($keyword);

        return $this->ask($engine, $prompt, $domain);
    }

    public function ask(string $engine, string $prompt, string $domain): LlmVisibilityResult
    {
        $endpoint = self::ENDPOINTS[$engine] ?? null;
        $model = self::MODEL_LABELS[$engine] ?? $engine;

        if ($endpoint === null) {
            return new LlmVisibilityResult(present: false, error: "Bilinmeyen LLM Scraper motoru: {$engine}", prompt: $prompt, model: $model);
        }

        try {
            $task = $this->client->postSingle($endpoint, [
                // DataForSEO keyword icindeki %-kacislarini cozer; duz % ve + kacirilir.
                'keyword' => str_replace(['%', '+'], ['%25', '%2B'], $prompt),
                'location_code' => $this->locationCode,
                'language_code' => $this->languageCode,
            ]);
        } catch (DataForSeoException $e) {
            return new LlmVisibilityResult(present: false, error: $e->getMessage(), prompt: $prompt, model: $model);
        }

        $result = $task['result'][0] ?? null;
        $markdown = is_array($result) && is_string($result['markdown'] ?? null) ? trim($result['markdown']) : '';

        if ($markdown === '') {
            return new LlmVisibilityResult(present: false, error: 'DataForSEO LLM Scraper boş cevap döndürdü.', prompt: $prompt, model: $model);
        }

        $citations = $this->citations(is_array($result['sources'] ?? null) ? $result['sources'] : []);

        return new LlmVisibilityResult(
            present: $this->isPresent($markdown, $citations, $domain),
            response: $markdown,
            prompt: $prompt,
            model: $model,
            citations: $citations,
        );
    }

    /**
     * Kaynak URL'leri; izleme parametreleri (utm_*, gclid...) atilir ve
     * tekrar edenler birlestirilir.
     *
     * @param  array<int, mixed>  $sources
     * @return list<string>
     */
    private function citations(array $sources): array
    {
        $urls = [];

        foreach ($sources as $source) {
            $url = is_array($source) ? ($source['url'] ?? null) : null;
            if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
                continue;
            }

            $urls[$this->withoutTracking($url)] = true;
        }

        return array_keys($urls);
    }

    private function withoutTracking(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return $url;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter(
            $query,
            static fn ($key) => !preg_match('/^utm_/i', (string) $key) && !in_array(strtolower((string) $key), ['gclid', 'fbclid', 'msclkid'], true),
            ARRAY_FILTER_USE_KEY,
        );

        return sprintf(
            '%s://%s%s%s%s',
            $parts['scheme'] ?? 'https',
            $parts['host'],
            isset($parts['port']) ? ':' . $parts['port'] : '',
            $parts['path'] ?? '/',
            $query !== [] ? '?' . http_build_query($query) : '',
        );
    }

    /**
     * @param  list<string>  $citations
     */
    private function isPresent(string $text, array $citations, string $domain): bool
    {
        if (LlmMentions::mentions($text, $domain)) {
            return true;
        }

        $domain = LlmMentions::normalizeHost($domain);
        foreach ($citations as $url) {
            $host = LlmMentions::normalizeHost((string) parse_url($url, PHP_URL_HOST));
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }
}
