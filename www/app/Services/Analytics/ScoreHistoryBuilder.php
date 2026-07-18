<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Check;
use Illuminate\Support\Collection;

/**
 * Var olan Check kayitlarindan (SERP pozisyonu, Lighthouse skorlari, AI
 * Overview atif durumu) domain/keyword arayuzlerinde grafikle gosterilecek
 * gunluk zaman serilerini uretir. Yeni bir API cagrisi/entegrasyon
 * gerektirmez - tamamen zaten toplanmis verinin agregasyonudur. Ayni
 * gunde birden fazla kontrol varsa (domain-genel grafikte farkli anahtar
 * kelimeler, ya da ayni kelimenin ayni gun icinde iki kez calistirilmasi)
 * o gunun degerleri ortalanir/oranlanir.
 */
final class ScoreHistoryBuilder
{
    /**
     * @param  Collection<int, Check>  $checks  Sirasi onemli degil, kendi icinde tarihe gore gruplanir.
     * @return array{labels: list<string>, ai_visibility: list<?float>, lighthouse_performance: list<?float>, lighthouse_seo: list<?float>, lighthouse_accessibility: list<?float>, lighthouse_best_practices: list<?float>, average_position: list<?float>}
     */
    public function groupedByDay(Collection $checks): array
    {
        $labels = [];
        $aiVisibility = [];
        $lighthousePerformance = [];
        $lighthouseSeo = [];
        $lighthouseAccessibility = [];
        $lighthouseBestPractices = [];
        $averagePosition = [];

        $byDay = $checks
            ->groupBy(fn (Check $check) => $check->created_at->format('Y-m-d'))
            ->sortKeys();

        foreach ($byDay as $day => $dayChecks) {
            $labels[] = $day;

            $present = $dayChecks->where('ai_overview_present', true);
            $cited = $present->where('ai_overview_target_cited', true);
            $aiVisibility[] = $present->isNotEmpty()
                ? round(($cited->count() / $present->count()) * 100, 1)
                : null;

            $lighthousePerformance[] = $this->average($dayChecks->pluck('lighthouse_performance'));
            $lighthouseSeo[] = $this->average($dayChecks->pluck('lighthouse_seo'));
            $lighthouseAccessibility[] = $this->average($dayChecks->pluck('lighthouse_accessibility'));
            $lighthouseBestPractices[] = $this->average($dayChecks->pluck('lighthouse_best_practices'));
            $averagePosition[] = $this->average($dayChecks->pluck('target_position'));
        }

        return [
            'labels' => $labels,
            'ai_visibility' => $aiVisibility,
            'lighthouse_performance' => $lighthousePerformance,
            'lighthouse_seo' => $lighthouseSeo,
            'lighthouse_accessibility' => $lighthouseAccessibility,
            'lighthouse_best_practices' => $lighthouseBestPractices,
            'average_position' => $averagePosition,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function average(Collection $values): ?float
    {
        $filtered = $values->filter(fn ($v) => $v !== null);

        return $filtered->isNotEmpty() ? round((float) $filtered->avg(), 1) : null;
    }
}
