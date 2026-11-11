<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

/**
 * Anahtar kelimeler icin aylik arama hacmi, CPC, rekabet, keyword
 * difficulty ve arama niyetini DataForSEO Labs'tan toplu olarak ceker.
 * https://docs.dataforseo.com/v3/dataforseo_labs/google/keyword_overview/live/
 *
 * Ucretlendirme gorev basina sabit ucret + kelime basina kucuk bir ucret
 * oldugu icin kelimeler tek tek degil tek istekte (en fazla 700) gonderilir.
 */
final class KeywordMetricsFetcher
{
    private const ENDPOINT = '/v3/dataforseo_labs/google/keyword_overview/live';

    /** DataForSEO'nun tek istekte kabul ettigi en fazla kelime sayisi. */
    public const BATCH_SIZE = 700;

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
     * @param  list<string>  $keywords
     * @return array<string, KeywordMetrics> kucuk harfli kelime => metrikler
     *
     * @throws DataForSeoException
     */
    public function fetch(array $keywords): array
    {
        $keywords = array_values(array_unique(array_filter(
            array_map(static fn (string $k) => mb_strtolower(trim($k)), $keywords),
            static fn (string $k) => $k !== '',
        )));

        $metrics = [];

        foreach (array_chunk($keywords, self::BATCH_SIZE) as $batch) {
            $task = $this->client->postSingle(self::ENDPOINT, [
                'keywords' => $batch,
                'location_code' => $this->locationCode,
                'language_code' => $this->languageCode,
            ]);

            $items = $task['result'][0]['items'] ?? [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $parsed = is_array($item) ? $this->parseItem($item) : null;
                if ($parsed !== null) {
                    $metrics[mb_strtolower($parsed->keyword)] = $parsed;
                }
            }
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function parseItem(array $item): ?KeywordMetrics
    {
        $keyword = $item['keyword'] ?? null;
        if (!is_string($keyword) || $keyword === '') {
            return null;
        }

        $info = is_array($item['keyword_info'] ?? null) ? $item['keyword_info'] : [];
        $difficulty = $item['keyword_properties']['keyword_difficulty'] ?? null;
        $intent = $item['search_intent_info']['main_intent'] ?? null;

        $monthly = [];
        foreach (is_array($info['monthly_searches'] ?? null) ? $info['monthly_searches'] : [] as $entry) {
            if (is_array($entry) && is_int($entry['year'] ?? null) && is_int($entry['month'] ?? null)) {
                $monthly[] = [
                    'year' => $entry['year'],
                    'month' => $entry['month'],
                    'search_volume' => (int) ($entry['search_volume'] ?? 0),
                ];
            }
        }

        // DataForSEO en yeni ayi once dondurur; grafik icin eskiden yeniye sirala.
        usort($monthly, static fn (array $a, array $b) => [$a['year'], $a['month']] <=> [$b['year'], $b['month']]);

        return new KeywordMetrics(
            keyword: $keyword,
            searchVolume: is_numeric($info['search_volume'] ?? null) ? (int) $info['search_volume'] : null,
            cpc: is_numeric($info['cpc'] ?? null) ? round((float) $info['cpc'], 2) : null,
            competition: is_numeric($info['competition'] ?? null) ? round((float) $info['competition'], 2) : null,
            keywordDifficulty: is_numeric($difficulty) ? (int) $difficulty : null,
            searchIntent: is_string($intent) && $intent !== '' ? $intent : null,
            monthlySearches: $monthly,
        );
    }
}
