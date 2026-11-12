<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Check;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\PromptExploration;
use App\Services\Drift\DomainCheckDrift;
use App\Support\AiAssistant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "Son N gunde ne kotulesti / ne iyilesti?" - yeni sorgu yapmaz, zaten
 * kaydedilmis gecmisi iki noktada karsilastirir:
 *  - Anahtar kelime: en son engellenmemis kontrol ile pencere basindan
 *    (now - days) once yapilmis en son engellenmemis kontrol -> Google
 *    pozisyonu, AI Overview cikmasi ve sitenin AI Overview kaynagi olmasi.
 *  - Yapay zeka asistanlari: pencere icindeki cevaplarda sitenin anilma
 *    orani, onceki ayni uzunluktaki pencereyle (saglayici bazinda).
 *  - Site: en son DomainCheck ile pencere basindan onceki DomainCheck
 *    arasindaki drift (bkz. DomainCheckDrift) + Search Console tiklamalari.
 * Kayiplar onem sirasina gore (impact) siralanir; yorumu MCP istemcisindeki
 * asistan yapar (bkz. GetVisibilityChanges).
 */
final class VisibilityChanges
{
    public const DEFAULT_DAYS = 7;
    public const MAX_DAYS = 90;

    /** Bu kadar ya da daha fazla sira dususu "kayip" sayilir. */
    private const POSITION_THRESHOLD = 3;

    /** Asistan anilma orani bu kadar puan degisirse raporlanir. */
    private const MENTION_RATE_THRESHOLD = 20;

    /** Search Console tiklamalari bu yuzde kadar degisirse raporlanir. */
    private const CLICKS_THRESHOLD_PCT = 20;

    /** Ilk 20 sonucun disina dusen pozisyon icin kullanilan sanal deger. */
    private const OUT_OF_RESULTS = 21;

    public function __construct(private readonly DomainCheckDrift $drift = new DomainCheckDrift())
    {
    }

    /**
     * @return array{days: int, since: string, compared_keywords: int, losses: list<array<string, mixed>>, gains: list<array<string, mixed>>, site_changes: list<string>}
     */
    public function forDomain(Domain $domain, int $days = self::DEFAULT_DAYS): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $since = now()->subDays($days);

        $changes = [];
        $compared = 0;

        $checksByKeyword = Check::query()
            ->whereIn('keyword_id', $domain->keywords()->pluck('id'))
            ->where(fn ($q) => $q->where('blocked', false)->orWhereNull('blocked'))
            ->select(['id', 'keyword_id', 'created_at', 'target_position', 'ai_overview_present', 'ai_overview_target_cited'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('keyword_id');

        foreach ($domain->keywords()->get(['id', 'keyword']) as $keyword) {
            $checks = $checksByKeyword->get($keyword->id, collect());
            $current = $checks->first();
            $baseline = $checks->first(fn (Check $c) => $c->created_at->lt($since));

            if ($current === null || $baseline === null || $current->created_at->lt($since)) {
                continue;
            }
            $compared++;

            $changes = [...$changes, ...$this->keywordChanges($keyword->keyword, $keyword->id, $baseline, $current)];
        }

        $changes = [...$changes, ...$this->assistantChanges($domain, $since, $days)];

        $siteChanges = [];
        $latestSiteCheck = $domain->domainChecks()->first();
        $baselineSiteCheck = $domain->domainChecks()->where('created_at', '<', $since)->first();
        if ($latestSiteCheck !== null && $baselineSiteCheck !== null && $latestSiteCheck->created_at->gte($since)) {
            $siteChanges = $this->drift->diff($latestSiteCheck, $baselineSiteCheck);
            $clicks = $this->clicksChange($baselineSiteCheck, $latestSiteCheck);
            if ($clicks !== null) {
                $changes[] = $clicks;
            }
        }

        usort($changes, fn (array $a, array $b) => $b['impact'] <=> $a['impact']);
        $losses = array_values(array_filter($changes, fn (array $c) => $c['direction'] === 'loss'));
        $gains = array_values(array_filter($changes, fn (array $c) => $c['direction'] === 'gain'));

        return [
            'days' => $days,
            'since' => $since->toIso8601String(),
            'compared_keywords' => $compared,
            'losses' => $losses,
            'gains' => $gains,
            'site_changes' => $siteChanges,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function keywordChanges(string $keyword, int $keywordId, Check $before, Check $after): array
    {
        $changes = [];
        $base = ['keyword' => $keyword, 'keyword_id' => $keywordId, 'before_at' => $before->created_at->toIso8601String(), 'after_at' => $after->created_at->toIso8601String()];

        if ($before->ai_overview_target_cited && !$after->ai_overview_target_cited) {
            $changes[] = $base + [
                'type' => 'ai_overview_citation_lost',
                'direction' => 'loss',
                'impact' => 100,
                'detail' => $after->ai_overview_present
                    ? 'AI Overview still appears but no longer cites the site.'
                    : 'AI Overview no longer appears, so the site lost its citation.',
            ];
        } elseif (!$before->ai_overview_target_cited && $after->ai_overview_target_cited) {
            $changes[] = $base + ['type' => 'ai_overview_citation_gained', 'direction' => 'gain', 'impact' => 100, 'detail' => 'The site is now cited in the AI Overview.'];
        } elseif ($before->ai_overview_present !== $after->ai_overview_present) {
            // Kaynak olunmayan bir AIO'nun cikmasi tiklamayi azaltabilir;
            // kaybolmasi organik sonuca tiklamayi artirabilir.
            $changes[] = $base + [
                'type' => $after->ai_overview_present ? 'ai_overview_appeared_without_citation' : 'ai_overview_disappeared',
                'direction' => $after->ai_overview_present ? 'loss' : 'gain',
                'impact' => 40,
                'detail' => $after->ai_overview_present
                    ? 'An AI Overview now appears above the results and does not cite the site.'
                    : 'The AI Overview (which did not cite the site) no longer appears.',
            ];
        }

        $from = $before->target_position;
        $to = $after->target_position;
        if ($from !== $to && ($from !== null || $to !== null)) {
            $delta = ($to ?? self::OUT_OF_RESULTS) - ($from ?? self::OUT_OF_RESULTS);
            $type = match (true) {
                $to === null => 'ranking_lost',
                $from === null => 'ranking_gained',
                default => $delta > 0 ? 'position_dropped' : 'position_improved',
            };

            if (abs($delta) >= self::POSITION_THRESHOLD || $to === null || $from === null) {
                // Ust siralardaki hareket daha degerli: 4->9 dususu 14->19'dan agir.
                $weight = min(80, abs($delta) * 4 + (min($from ?? self::OUT_OF_RESULTS, $to ?? self::OUT_OF_RESULTS) <= 10 ? 20 : 0));
                $changes[] = $base + [
                    'type' => $type,
                    'direction' => $delta > 0 ? 'loss' : 'gain',
                    'impact' => $type === 'ranking_lost' ? 90 : $weight,
                    'position_before' => $from,
                    'position_after' => $to,
                ];
            }
        }

        return $changes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assistantChanges(Domain $domain, Carbon $since, int $days): array
    {
        $explorations = $domain->promptExplorations()
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', $since->copy()->subDays($days))
            ->get(['id', 'created_at', 'results']);

        $current = $this->mentionRates($explorations->filter(fn (PromptExploration $e) => $e->created_at->gte($since)));
        $previous = $this->mentionRates($explorations->filter(fn (PromptExploration $e) => $e->created_at->lt($since)));

        $changes = [];
        foreach ($current as $provider => $now) {
            $then = $previous[$provider] ?? null;
            if ($then === null) {
                continue;
            }
            $delta = $now['rate'] - $then['rate'];
            if (abs($delta) < self::MENTION_RATE_THRESHOLD) {
                continue;
            }
            $changes[] = [
                'type' => $delta < 0 ? 'assistant_mentions_dropped' : 'assistant_mentions_rose',
                'direction' => $delta < 0 ? 'loss' : 'gain',
                'impact' => min(90, abs($delta)),
                'assistant' => AiAssistant::label($provider),
                'mention_rate_before_pct' => $then['rate'],
                'mention_rate_after_pct' => $now['rate'],
                'answers_before' => $then['answers'],
                'answers_after' => $now['answers'],
            ];
        }

        return $changes;
    }

    /**
     * @param  Collection<int, PromptExploration>  $explorations
     * @return array<string, array{rate: int, answers: int}>
     */
    private function mentionRates(Collection $explorations): array
    {
        $counts = [];
        foreach ($explorations as $exploration) {
            foreach ($exploration->results ?? [] as $provider => $result) {
                if (!is_array($result) || ($result['error'] ?? null) !== null) {
                    continue;
                }
                $counts[$provider]['answers'] = ($counts[$provider]['answers'] ?? 0) + 1;
                $counts[$provider]['mentioned'] = ($counts[$provider]['mentioned'] ?? 0) + (($result['present'] ?? false) ? 1 : 0);
            }
        }

        return array_map(fn (array $c) => [
            'rate' => (int) round($c['mentioned'] / $c['answers'] * 100),
            'answers' => $c['answers'],
        ], $counts);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function clicksChange(DomainCheck $before, DomainCheck $after): ?array
    {
        $from = $before->gsc['clicks'] ?? null;
        $to = $after->gsc['clicks'] ?? null;
        if (!is_numeric($from) || !is_numeric($to) || (float) $from <= 0.0) {
            return null;
        }

        $pct = (int) round(((float) $to - (float) $from) / (float) $from * 100);
        if (abs($pct) < self::CLICKS_THRESHOLD_PCT) {
            return null;
        }

        return [
            'type' => $pct < 0 ? 'search_console_clicks_dropped' : 'search_console_clicks_rose',
            'direction' => $pct < 0 ? 'loss' : 'gain',
            'impact' => min(85, abs($pct)),
            'clicks_before' => (int) $from,
            'clicks_after' => (int) $to,
            'change_pct' => $pct,
        ];
    }
}
