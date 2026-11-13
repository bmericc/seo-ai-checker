<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Domain;
use App\Models\DomainCompetitor;
use App\Services\Keywords\KeywordSuggester;
use Illuminate\Support\Collection;

/**
 * Capraz kontrol: takip edilen rakiplerin one ciktigi anahtar kelimelerden
 * bizim listemizde OLMAYANLARI cikarir. Birden cok rakipte ortak gecen
 * kelime once gelir (sektorun ortak sorgusu, tek rakibin markasi degil),
 * sonra arama hacmi, sonra rakiplerin en iyi pozisyonu.
 *
 * Takip edilen ve "oneri olarak reddedilmis" (dismissed_keyword_suggestions)
 * kelimeler haric tutulur; boylece bir kelime eklendiginde ya da
 * reddedildiginde listeden hemen duser.
 */
final class CompetitorKeywordGap
{
    public const MAX_ROWS = 100;

    /**
     * @param  Collection<int, DomainCompetitor>  $competitors  Sadece tracked rakipler kullanilir.
     * @return list<array{keyword: string, competitor_count: int, search_volume: ?int, best_position: ?int, competitors: list<array{domain: string, position: ?int, url: ?string}>}>
     */
    public static function forDomain(Domain $domain, Collection $competitors): array
    {
        $exclude = [];
        foreach ($domain->keywords as $keyword) {
            $exclude[KeywordSuggester::lower(trim($keyword->keyword))] = true;
        }
        foreach ($domain->dismissed_keyword_suggestions ?? [] as $dismissed) {
            $exclude[KeywordSuggester::lower(trim((string) $dismissed))] = true;
        }

        return self::analyze($competitors->filter(fn (DomainCompetitor $c) => $c->isTracked()), $exclude);
    }

    /**
     * @param  Collection<int, DomainCompetitor>  $competitors
     * @param  array<string, true>  $exclude  Kucuk harfli, haric tutulacak kelimeler.
     */
    public static function analyze(Collection $competitors, array $exclude): array
    {
        /** @var array<string, array{keyword: string, search_volume: ?int, competitors: array<string, array{domain: string, position: ?int, url: ?string}>}> $rows */
        $rows = [];

        foreach ($competitors as $competitor) {
            foreach ($competitor->keywords ?? [] as $item) {
                $keyword = trim((string) ($item['keyword'] ?? ''));
                if ($keyword === '') {
                    continue;
                }

                $key = KeywordSuggester::lower($keyword);
                if (isset($exclude[$key])) {
                    continue;
                }

                $rows[$key] ??= ['keyword' => $key, 'search_volume' => null, 'competitors' => []];

                $volume = isset($item['search_volume']) ? (int) $item['search_volume'] : null;
                if ($volume !== null) {
                    $rows[$key]['search_volume'] = max($rows[$key]['search_volume'] ?? 0, $volume);
                }

                $position = isset($item['position']) ? (int) $item['position'] : null;
                $existing = $rows[$key]['competitors'][$competitor->competitor_domain] ?? null;
                if ($existing === null || ($position !== null && ($existing['position'] === null || $position < $existing['position']))) {
                    $rows[$key]['competitors'][$competitor->competitor_domain] = [
                        'domain' => $competitor->competitor_domain,
                        'position' => $position,
                        'url' => isset($item['url']) && is_string($item['url']) ? $item['url'] : null,
                    ];
                }
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $positions = array_filter(array_column($row['competitors'], 'position'), static fn ($p) => $p !== null);
            $competitorRows = array_values($row['competitors']);
            usort($competitorRows, static fn (array $a, array $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX));

            $result[] = [
                'keyword' => $row['keyword'],
                'competitor_count' => count($competitorRows),
                'search_volume' => $row['search_volume'],
                'best_position' => $positions === [] ? null : min($positions),
                'competitors' => $competitorRows,
            ];
        }

        usort($result, static fn (array $a, array $b) => $b['competitor_count'] <=> $a['competitor_count']
            ?: ($b['search_volume'] ?? -1) <=> ($a['search_volume'] ?? -1)
            ?: ($a['best_position'] ?? PHP_INT_MAX) <=> ($b['best_position'] ?? PHP_INT_MAX)
            ?: strcmp($a['keyword'], $b['keyword']));

        return array_slice($result, 0, self::MAX_ROWS);
    }
}
