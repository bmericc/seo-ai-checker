<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Keyword;
use App\Support\Domain as DomainHelper;
use Illuminate\Support\Collection;

/**
 * "Rakip analizi" katmani - yeni bir sorgu/entegrasyon yapmaz, zaten her
 * kontrolde kaydedilen SERP organik sonuclarindan (Check.organic_results)
 * hangi domain'lerin izlenen anahtar kelimelerde sizinle birlikte SIK
 * cikip cikmadigini cikarir. Her anahtar kelimenin sadece EN SON
 * kontrolune (latestCheck) bakar - eski/tekrarlanan kontrol gecmisi
 * "kac kere kontrol edildi" gibi alakasiz bir sinyalle rakip siralamasini
 * carpitmasin diye; boylece "kac FARKLI anahtar kelimede beraber
 * cikiyor" temiz bir genislik (breadth) sinyali olur.
 */
final class CompetitorAnalyzer
{
    private const MAX_RESULTS = 10;

    /**
     * @param  Collection<int, Keyword>  $keywords  Domain'e ait anahtar kelimeler (latestCheck eager-load edilmis olmali).
     * @return array{tracked_keyword_count: int, competitors: list<array{domain: string, keyword_count: int, average_position: float, best_position: int, keywords: list<string>}>}
     */
    public function frequentCompetitors(string $ownDomain, Collection $keywords): array
    {
        $trackedKeywordCount = 0;
        $byCompetitor = [];

        foreach ($keywords as $keyword) {
            $check = $keyword->latestCheck;
            if ($check === null || $check->blocked || empty($check->organic_results)) {
                continue;
            }

            $trackedKeywordCount++;

            foreach ($check->organic_results as $result) {
                $competitorDomain = $result['domain'] ?? null;
                if (!is_string($competitorDomain) || $competitorDomain === '' || DomainHelper::equals($competitorDomain, $ownDomain)) {
                    continue;
                }

                $byCompetitor[$competitorDomain]['positions'][] = (int) $result['position'];
                $byCompetitor[$competitorDomain]['keywords'][$keyword->keyword] = true;
            }
        }

        $competitors = [];
        foreach ($byCompetitor as $domain => $data) {
            $positions = $data['positions'];
            $competitors[] = [
                'domain' => $domain,
                'keyword_count' => count($data['keywords']),
                'average_position' => round(array_sum($positions) / count($positions), 1),
                'best_position' => min($positions),
                'keywords' => array_keys($data['keywords']),
            ];
        }

        usort($competitors, fn (array $a, array $b) => $b['keyword_count'] <=> $a['keyword_count'] ?: $a['average_position'] <=> $b['average_position']);

        return [
            'tracked_keyword_count' => $trackedKeywordCount,
            'competitors' => array_slice($competitors, 0, self::MAX_RESULTS),
        ];
    }
}
