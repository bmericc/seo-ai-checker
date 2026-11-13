<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

/**
 * Bir domain'in Google'da gercekten siralandigi anahtar kelimeleri
 * DataForSEO Labs'tan ceker (rakip anahtar kelime boslugu icin).
 * https://docs.dataforseo.com/v3/dataforseo_labs/google/ranked_keywords/live/
 *
 * Ucret gorev basina sabit + donen satir basina kucuk bir ucrettir; LIMIT
 * satirla sinirli tutulur ve sadece ilk MAX_POSITION'daki siralamalar
 * istenir - 50. sayfadaki bir siralama "rakibin one ciktigi" kelime degildir.
 */
final class RankedKeywordsFetcher
{
    private const ENDPOINT = '/v3/dataforseo_labs/google/ranked_keywords/live';

    public const LIMIT = 200;

    public const MAX_POSITION = 20;

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
     * @return list<array{keyword: string, position: ?int, search_volume: ?int, url: ?string, score: ?float}>
     *
     * @throws DataForSeoException
     */
    public function fetch(string $targetDomain): array
    {
        $task = $this->client->postSingle(self::ENDPOINT, [
            'target' => $targetDomain,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
            'limit' => self::LIMIT,
            'filters' => [['ranked_serp_element.serp_item.rank_group', '<=', self::MAX_POSITION]],
            'order_by' => ['keyword_data.keyword_info.search_volume,desc'],
        ]);

        $items = $task['result'][0]['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $keywords = [];
        foreach ($items as $item) {
            $parsed = is_array($item) ? $this->parseItem($item) : null;
            if ($parsed === null) {
                continue;
            }

            // Ayni kelime icin birden cok URL siralanabilir; en iyi pozisyonu tut.
            $key = mb_strtolower($parsed['keyword']);
            $existing = $keywords[$key] ?? null;
            if ($existing === null || ($parsed['position'] ?? PHP_INT_MAX) < ($existing['position'] ?? PHP_INT_MAX)) {
                $keywords[$key] = $parsed;
            }
        }

        return array_values($keywords);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return ?array{keyword: string, position: ?int, search_volume: ?int, url: ?string, score: ?float}
     */
    private function parseItem(array $item): ?array
    {
        $keyword = $item['keyword_data']['keyword'] ?? null;
        if (!is_string($keyword) || trim($keyword) === '') {
            return null;
        }

        $serpItem = is_array($item['ranked_serp_element']['serp_item'] ?? null) ? $item['ranked_serp_element']['serp_item'] : [];
        $volume = $item['keyword_data']['keyword_info']['search_volume'] ?? null;
        $position = $serpItem['rank_group'] ?? null;
        $url = $serpItem['url'] ?? null;

        return [
            'keyword' => trim($keyword),
            'position' => is_numeric($position) ? (int) $position : null,
            'search_volume' => is_numeric($volume) ? (int) $volume : null,
            'url' => is_string($url) && $url !== '' ? $url : null,
            'score' => null,
        ];
    }
}
