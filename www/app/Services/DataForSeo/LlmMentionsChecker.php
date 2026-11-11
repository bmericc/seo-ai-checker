<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

use App\Services\Llm\LlmMentions;

/**
 * Prompt calistirmadan, DataForSEO'nun topladigi AI cevap veritabaninda bir
 * domain'in kac cevapta gectigini ve hangi sorularda gectigini getirir.
 * https://docs.dataforseo.com/v3/ai_optimization/llm_mentions/aggregated_metrics/live/
 * https://docs.dataforseo.com/v3/ai_optimization/llm_mentions/search/live/
 *
 * Iki platform vardir: "google" (AI Overview / AI Mode, Turkiye verisi
 * mevcut) ve "chat_gpt" (DataForSEO dokumantasyonuna gore yalnizca ABD /
 * Ingilizce verisi var - bu yuzden konumu sabit 2840/en). Domain-geneli,
 * kullanicidan bagimsiz bir veri oldugu icin DomainFact'te paylasilir.
 */
final class LlmMentionsChecker
{
    private const AGGREGATED_ENDPOINT = '/v3/ai_optimization/llm_mentions/aggregated_metrics/live';
    private const SEARCH_ENDPOINT = '/v3/ai_optimization/llm_mentions/search/live';

    private const CHATGPT_LOCATION_CODE = 2840;
    private const CHATGPT_LANGUAGE_CODE = 'en';

    private const TOP_QUESTIONS_LIMIT = 10;

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
     * @return array{platforms: array<string, array<string, mixed>>}
     */
    public function check(string $domain): array
    {
        $platforms = [];

        foreach ($this->platforms() as $platform => [$locationCode, $languageCode]) {
            $platforms[$platform] = $this->checkPlatform($domain, $platform, $locationCode, $languageCode);
        }

        return ['platforms' => $platforms];
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    private function platforms(): array
    {
        return [
            'google' => [$this->locationCode, $this->languageCode],
            'chat_gpt' => [self::CHATGPT_LOCATION_CODE, self::CHATGPT_LANGUAGE_CODE],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPlatform(string $domain, string $platform, int $locationCode, string $languageCode): array
    {
        $base = [
            'target' => [['domain' => $domain, 'include_subdomains' => true]],
            'platform' => $platform,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
        ];

        $data = [
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'mentions' => null,
            'ai_search_volume' => null,
            'impressions' => null,
            'top_questions' => [],
            'error' => null,
        ];

        try {
            $aggregated = $this->client->postSingle(self::AGGREGATED_ENDPOINT, $base);
            $groups = $aggregated['result'][0]['total']['platform'] ?? [];

            foreach (is_array($groups) ? $groups : [] as $group) {
                if (!is_array($group)) {
                    continue;
                }
                foreach (['mentions', 'ai_search_volume', 'impressions'] as $metric) {
                    if (is_numeric($group[$metric] ?? null)) {
                        $data[$metric] = ($data[$metric] ?? 0) + (int) $group[$metric];
                    }
                }
            }

            $search = $this->client->postSingle(self::SEARCH_ENDPOINT, $base + [
                'limit' => self::TOP_QUESTIONS_LIMIT,
                'order_by' => ['ai_search_volume,desc'],
            ]);
            $items = $search['result'][0]['items'] ?? [];

            foreach (is_array($items) ? $items : [] as $item) {
                $question = is_array($item) ? ($item['question'] ?? null) : null;
                if (!is_string($question) || $question === '') {
                    continue;
                }

                $sources = is_array($item['sources'] ?? null) ? $item['sources'] : [];
                $data['top_questions'][] = [
                    'question' => $question,
                    'ai_search_volume' => is_numeric($item['ai_search_volume'] ?? null) ? (int) $item['ai_search_volume'] : null,
                    'cited' => $this->sourcesCite($sources, $domain),
                    'sources_count' => count($sources),
                ];
            }
        } catch (DataForSeoException $e) {
            $data['error'] = $e->getMessage();
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $sources
     */
    private function sourcesCite(array $sources, string $domain): bool
    {
        $domain = LlmMentions::normalizeHost($domain);

        foreach ($sources as $source) {
            $host = is_array($source)
                ? ($source['domain'] ?? parse_url((string) ($source['url'] ?? ''), PHP_URL_HOST))
                : null;
            $host = LlmMentions::normalizeHost((string) $host);

            if ($host !== '' && ($host === $domain || str_ends_with($host, '.' . $domain))) {
                return true;
            }
        }

        return false;
    }
}
