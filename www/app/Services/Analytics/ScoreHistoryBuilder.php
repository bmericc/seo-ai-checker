<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Check;
use App\Models\DomainCheck;
use App\Services\Ga4\Ga4ChannelGroups;
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
     * DomainCheck.crux/gsc/ga4 (site-genel, keyword'e bagli olmayan
     * kontroller - robots.txt, sitemap, CrUX, Search Console, GA4) icin ayni
     * gunluk agregasyon mantigi. Bunlar keyword kontrollerinden ayri bir
     * tabloda (domain_checks) tutuldugu icin groupedByDay'den ayri bir
     * method - farkli bir model, farkli sutunlar.
     *
     * @param  Collection<int, DomainCheck>  $domainChecks  Sirasi onemli degil, kendi icinde tarihe gore gruplanir.
     * @return array{labels: list<string>, crux_lcp: list<?float>, crux_fcp: list<?float>, crux_ttfb: list<?float>, crux_inp: list<?float>, crux_cls: list<?float>, gsc_clicks: list<?float>, gsc_impressions: list<?float>, gsc_ctr: list<?float>, gsc_average_position: list<?float>, ga4_total_sessions: list<?float>, ga4_organic_sessions: list<?float>, ga4_non_organic_sessions: list<?float>, ga4_active_users: list<?float>, ga4_direct_sessions: list<?float>, ga4_referral_sessions: list<?float>, ga4_social_sessions: list<?float>, ga4_paid_sessions: list<?float>, ga4_other_sessions: list<?float>}
     */
    public function domainCheckHistory(Collection $domainChecks): array
    {
        $labels = [];
        $cruxLcp = [];
        $cruxFcp = [];
        $cruxTtfb = [];
        $cruxInp = [];
        $cruxCls = [];
        $gscClicks = [];
        $gscImpressions = [];
        $gscCtr = [];
        $gscAveragePosition = [];
        $ga4TotalSessions = [];
        $ga4OrganicSessions = [];
        $ga4NonOrganicSessions = [];
        $ga4ActiveUsers = [];
        $ga4ChannelBuckets = array_fill_keys(Ga4ChannelGroups::BUCKETS, []);

        $byDay = $domainChecks
            ->groupBy(fn (DomainCheck $check) => $check->created_at->format('Y-m-d'))
            ->sortKeys();

        foreach ($byDay as $day => $dayChecks) {
            $labels[] = $day;

            $cruxLcp[] = $this->averageCruxMetric($dayChecks, 'largest_contentful_paint');
            $cruxFcp[] = $this->averageCruxMetric($dayChecks, 'first_contentful_paint');
            $cruxTtfb[] = $this->averageCruxMetric($dayChecks, 'experimental_time_to_first_byte');
            $cruxInp[] = $this->averageCruxMetric($dayChecks, 'interaction_to_next_paint');
            // CLS is a small unitless ratio (0-0.25+, "good" cuts off at
            // 0.1) - the 1-decimal rounding used for every other metric
            // would blur it into meaningless 0.0/0.1/0.2 buckets, so it
            // gets 3 decimals instead.
            $cruxCls[] = $this->averageCruxMetric($dayChecks, 'cumulative_layout_shift', precision: 3);

            $gscClicks[] = $this->average($dayChecks->map(fn (DomainCheck $c) => $c->gsc['clicks'] ?? null));
            $gscImpressions[] = $this->average($dayChecks->map(fn (DomainCheck $c) => $c->gsc['impressions'] ?? null));
            $gscCtr[] = $this->average($dayChecks->map(fn (DomainCheck $c) => isset($c->gsc['ctr']) ? $c->gsc['ctr'] * 100 : null));
            $gscAveragePosition[] = $this->average($dayChecks->map(fn (DomainCheck $c) => $c->gsc['average_position'] ?? null));

            $ga4TotalSessions[] = $this->average($dayChecks->map(fn (DomainCheck $c) => $c->ga4['total_sessions'] ?? null));
            $ga4OrganicSessions[] = $this->average($dayChecks->map(fn (DomainCheck $c) => $c->ga4['organic_sessions'] ?? null));
            // Not stored separately - derived from total minus organic so
            // checks recorded before this series existed still plot it.
            $ga4NonOrganicSessions[] = $this->average($dayChecks->map(fn (DomainCheck $c) => isset($c->ga4['total_sessions'], $c->ga4['organic_sessions'])
                ? max(0, $c->ga4['total_sessions'] - $c->ga4['organic_sessions'])
                : null));
            $ga4ActiveUsers[] = $this->average($dayChecks->map(fn (DomainCheck $c) => $c->ga4['active_users'] ?? null));

            // Checks recorded before channel_sessions was stored group to
            // null, so those days stay gaps instead of dropping to 0.
            $dayGroups = $dayChecks->map(fn (DomainCheck $c) => Ga4ChannelGroups::group($c->ga4['channel_sessions'] ?? null));
            foreach (Ga4ChannelGroups::BUCKETS as $bucket) {
                $ga4ChannelBuckets[$bucket][] = $this->average($dayGroups->map(fn (?array $groups) => $groups[$bucket] ?? null));
            }
        }

        return [
            'labels' => $labels,
            'crux_lcp' => $cruxLcp,
            'crux_fcp' => $cruxFcp,
            'crux_ttfb' => $cruxTtfb,
            'crux_inp' => $cruxInp,
            'crux_cls' => $cruxCls,
            'gsc_clicks' => $gscClicks,
            'gsc_impressions' => $gscImpressions,
            'gsc_ctr' => $gscCtr,
            'gsc_average_position' => $gscAveragePosition,
            'ga4_total_sessions' => $ga4TotalSessions,
            'ga4_organic_sessions' => $ga4OrganicSessions,
            'ga4_non_organic_sessions' => $ga4NonOrganicSessions,
            'ga4_active_users' => $ga4ActiveUsers,
            'ga4_direct_sessions' => $ga4ChannelBuckets[Ga4ChannelGroups::DIRECT],
            'ga4_referral_sessions' => $ga4ChannelBuckets[Ga4ChannelGroups::REFERRAL],
            'ga4_social_sessions' => $ga4ChannelBuckets[Ga4ChannelGroups::SOCIAL],
            'ga4_paid_sessions' => $ga4ChannelBuckets[Ga4ChannelGroups::PAID],
            'ga4_other_sessions' => $ga4ChannelBuckets[Ga4ChannelGroups::OTHER],
        ];
    }

    /**
     * @param  Collection<int, DomainCheck>  $dayChecks
     */
    private function averageCruxMetric(Collection $dayChecks, string $metricKey, int $precision = 1): ?float
    {
        return $this->average($dayChecks->map(fn (DomainCheck $c) => $c->crux['metrics'][$metricKey]['p75'] ?? null), $precision);
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function average(Collection $values, int $precision = 1): ?float
    {
        $filtered = $values->filter(fn ($v) => $v !== null);

        return $filtered->isNotEmpty() ? round((float) $filtered->avg(), $precision) : null;
    }
}
