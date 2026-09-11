<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Domain;
use App\Models\Keyword;
use App\Services\Drift\DomainCheckDrift;

/**
 * Haftalik e-posta raporu icin bir domain'in son durumunu ozetler - her
 * anahtar kelimenin EN SON check'i "bu hafta", en az 6 gun once cekilmis
 * check'i (varsa) "gecen hafta" kabul edilip aradaki degisim (trend)
 * hesaplanir. Check'ler duzenli/gunluk calismayabilir (kullanici manuel
 * "Kontrol Et"e basar) - bu yuzden "tam olarak 7 gun once" yerine "en az
 * 6 gun once cekilmis EN SON check" araniyor, boylece duzensiz araliklarla
 * calisan domainlerde de bir onceki nokta bulunabiliyor.
 */
final class WeeklySeoReportBuilder
{
    private const PREVIOUS_MIN_AGE_DAYS = 6;

    public function __construct(private readonly DomainCheckDrift $drift)
    {
    }

    /**
     * Domain'e ait hic kontrol/anahtar kelime yoksa null doner (raporda
     * gosterilecek bir sey yok) - cagiran taraf (bkz. SendWeeklySeoReportJob)
     * bu domain'i atlar.
     *
     * @return ?array<string, mixed>
     */
    public function build(Domain $domain): ?array
    {
        $domain->loadMissing(['keywords.checks', 'fact', 'domainChecks']);

        $keywordRows = [];
        foreach ($domain->keywords as $keyword) {
            $row = $this->keywordRow($keyword);
            if ($row !== null) {
                $keywordRows[] = $row;
            }
        }

        $domainChecks = $domain->domainChecks;
        $latestDomainCheck = $domainChecks->first();
        $previousDomainCheck = $domainChecks->get(1);

        if (empty($keywordRows) && $latestDomainCheck === null) {
            return null;
        }

        return [
            'domain' => $domain,
            'fact' => $domain->fact,
            'domain_check' => $latestDomainCheck,
            'drift_changes' => ($latestDomainCheck && $previousDomainCheck)
                ? $this->drift->diff($latestDomainCheck, $previousDomainCheck)
                : [],
            'keywords' => $keywordRows,
            'aggregates' => $this->aggregates($keywordRows, (bool) $domain->llm_visibility_enabled),
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function keywordRow(Keyword $keyword): ?array
    {
        $current = $keyword->checks->first();
        if ($current === null) {
            return null;
        }

        $previous = $keyword->checks->first(
            fn ($check) => $check->created_at->lte($current->created_at->copy()->subDays(self::PREVIOUS_MIN_AGE_DAYS))
        );

        return [
            'keyword' => $keyword->keyword,
            'position' => $current->target_position,
            'previous_position' => $previous?->target_position,
            'ai_overview_present' => $current->ai_overview_present,
            'ai_overview_target_cited' => $current->ai_overview_target_cited,
            'eeat_score' => $current->eeat_score,
            'previous_eeat_score' => $previous?->eeat_score,
            'lighthouse_performance' => $current->lighthouse_performance,
            'previous_lighthouse_performance' => $previous?->lighthouse_performance,
            'llm_visibility' => $current->llm_visibility,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $keywordRows
     * @return array<string, mixed>
     */
    private function aggregates(array $keywordRows, bool $llmVisibilityEnabled): array
    {
        $positions = array_filter(array_column($keywordRows, 'position'), fn ($v) => $v !== null);
        $previousPositions = array_filter(array_column($keywordRows, 'previous_position'), fn ($v) => $v !== null);
        $eeatScores = array_filter(array_column($keywordRows, 'eeat_score'), fn ($v) => $v !== null);
        $previousEeatScores = array_filter(array_column($keywordRows, 'previous_eeat_score'), fn ($v) => $v !== null);
        $lighthouseScores = array_filter(array_column($keywordRows, 'lighthouse_performance'), fn ($v) => $v !== null);
        $previousLighthouseScores = array_filter(array_column($keywordRows, 'previous_lighthouse_performance'), fn ($v) => $v !== null);

        $aiOverviewPresent = array_filter($keywordRows, fn ($r) => $r['ai_overview_present']);
        $aiOverviewCited = array_filter($aiOverviewPresent, fn ($r) => $r['ai_overview_target_cited']);

        $llmPassCounts = [];
        $llmCheckedCount = 0;
        if ($llmVisibilityEnabled) {
            foreach ($keywordRows as $row) {
                if (empty($row['llm_visibility'])) {
                    continue;
                }
                $llmCheckedCount++;
                foreach ($row['llm_visibility'] as $provider => $result) {
                    $llmPassCounts[$provider] = ($llmPassCounts[$provider] ?? 0) + ($result['present'] ? 1 : 0);
                }
            }
        }

        return [
            'keyword_count' => count($keywordRows),
            'avg_position' => $positions !== [] ? round(array_sum($positions) / count($positions), 1) : null,
            'avg_previous_position' => $previousPositions !== [] ? round(array_sum($previousPositions) / count($previousPositions), 1) : null,
            'avg_eeat' => $eeatScores !== [] ? round(array_sum($eeatScores) / count($eeatScores), 1) : null,
            'avg_previous_eeat' => $previousEeatScores !== [] ? round(array_sum($previousEeatScores) / count($previousEeatScores), 1) : null,
            'avg_lighthouse_performance' => $lighthouseScores !== [] ? round(array_sum($lighthouseScores) / count($lighthouseScores), 1) : null,
            'avg_previous_lighthouse_performance' => $previousLighthouseScores !== [] ? round(array_sum($previousLighthouseScores) / count($previousLighthouseScores), 1) : null,
            'ai_overview_present_count' => count($aiOverviewPresent),
            'ai_overview_cited_count' => count($aiOverviewCited),
            'llm_pass_counts' => $llmPassCounts,
            'llm_checked_count' => $llmCheckedCount,
        ];
    }
}
