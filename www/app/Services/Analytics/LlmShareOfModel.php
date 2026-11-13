<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Domain;
use App\Models\PromptExploration;
use App\Services\Llm\LlmMentions;
use Illuminate\Support\Collection;

/**
 * "Share of Model" matrisi - yeni sorgu yapmaz, kaydedilmis yapay zeka
 * cevaplarindan (PromptExploration.results: Prompt Explorer, otomatik
 * anahtar kelime kontrolleri ve MCP check_ai_answer) hangi domain'lerin
 * hangi asistan tarafindan ne siklikla anildigini cikarir.
 *
 * Satirlar: domain'in kendisi + verilen rakipler (SERP rakip analizinden)
 * + cevaplarda en sik anilan diger siteler. Hucre = o asistanin hatasiz
 * cevaplarinin yuzde kacinda o domain (ya da alt domain'i) gecti.
 *
 * Sinir: tespit yalnizca cevap metninde/kaynaklarda gecen domain adlarina
 * dayanir (bkz. LlmMentions::domainsIn) - "trendyol.com" yerine sadece
 * "Trendyol" diye anilan bir marka sayilmaz. Domain'in kendisi icin
 * kaydedilmis "present" bayragi kullanilir, cunku o marka adini da kapsar.
 */
final class LlmShareOfModel
{
    public const MAX_ROWS = 10;

    /** Matrise giren cevaplarin zaman penceresi ve ust siniri. */
    public const DAYS = 90;
    public const MAX_EXPLORATIONS = 500;

    /** SERP rakip analizinden her durumda satir olarak eklenen rakip sayisi. */
    public const SERP_COMPETITORS = 5;

    /** Secilmis rakiplerden matrise girecek en fazla domain. */
    public const MAX_TRACKED_COMPETITORS = 10;

    /**
     * Domain'in son DAYS gunluk tamamlanmis AI cevaplarindan matris (arayuz
     * ve MCP get_competitors ayni sorguyu kullanir).
     *
     * Kullanicinin sectigi (tracked) rakipler varsa satirlar onlardan
     * olusur; yoksa SERP kesfinin ilk SERP_COMPETITORS domain'ine duser.
     *
     * @param  array{competitors: list<array{domain: string}>}  $competitorAnalysis  CompetitorAnalyzer::frequentCompetitors() ciktisi.
     * @param  list<string>  $trackedCompetitors  Secilmis rakip domainler.
     */
    public static function forDomain(Domain $domain, array $competitorAnalysis, array $trackedCompetitors = []): array
    {
        $competitors = $trackedCompetitors !== []
            ? array_slice(array_values($trackedCompetitors), 0, self::MAX_TRACKED_COMPETITORS)
            : array_slice(array_column($competitorAnalysis['competitors'], 'domain'), 0, self::SERP_COMPETITORS);

        return self::analyze(
            $domain->domain,
            $domain->promptExplorations()
                ->whereNotNull('completed_at')
                ->where('created_at', '>=', now()->subDays(self::DAYS))
                ->limit(self::MAX_EXPLORATIONS)
                ->get(['id', 'results']),
            $competitors,
        );
    }

    /**
     * @param  Collection<int, PromptExploration>  $explorations
     * @param  list<string>  $competitors  Her durumda satir olarak gosterilecek rakip domainler.
     * @return array{answers: int, providers: list<string>, answered: array<string, int>, rows: list<array{domain: string, own: bool, rate: int, mentions: int, by_provider: array<string, ?int>}>}
     */
    public static function analyze(string $ownDomain, Collection $explorations, array $competitors = []): array
    {
        $own = LlmMentions::normalizeHost($ownDomain);

        /** @var list<array{provider: string, own: bool, hosts: list<string>}> $answers */
        $answers = [];
        $hostTotals = [];

        foreach ($explorations as $exploration) {
            foreach ($exploration->results ?? [] as $provider => $result) {
                if (!is_array($result) || ($result['error'] ?? null) !== null || trim((string) ($result['response'] ?? '')) === '') {
                    continue;
                }

                $hosts = array_keys(LlmMentions::domainsIn((string) $result['response'], $result['citations'] ?? []));
                $answers[] = [
                    'provider' => (string) $provider,
                    'own' => (bool) ($result['present'] ?? self::matches($hosts, $own)),
                    'hosts' => $hosts,
                ];

                foreach ($hosts as $host) {
                    if (!self::isSameSite($host, $own)) {
                        $hostTotals[$host] = ($hostTotals[$host] ?? 0) + 1;
                    }
                }
            }
        }

        $answered = [];
        foreach ($answers as $answer) {
            $answered[$answer['provider']] = ($answered[$answer['provider']] ?? 0) + 1;
        }
        arsort($answered);
        $providers = array_keys($answered);

        $candidates = [$own];
        foreach ($competitors as $competitor) {
            $candidates[] = LlmMentions::normalizeHost($competitor);
        }
        arsort($hostTotals);
        foreach (array_keys($hostTotals) as $host) {
            $candidates[] = $host;
        }
        $candidates = array_slice(self::dedupe($candidates), 0, self::MAX_ROWS);

        $rows = [];
        foreach ($candidates as $candidate) {
            $isOwn = $candidate === $own;
            $byProvider = array_fill_keys($providers, 0);
            $mentions = 0;

            foreach ($answers as $answer) {
                $hit = $isOwn ? $answer['own'] : self::matches($answer['hosts'], $candidate);
                if ($hit) {
                    $byProvider[$answer['provider']]++;
                    $mentions++;
                }
            }

            $rows[] = [
                'domain' => $candidate,
                'own' => $isOwn,
                'mentions' => $mentions,
                'rate' => $answers === [] ? 0 : (int) round($mentions / count($answers) * 100),
                'by_provider' => array_map(
                    fn (string $provider) => (int) round($byProvider[$provider] / $answered[$provider] * 100),
                    array_combine($providers, $providers) ?: [],
                ),
            ];
        }

        // Kendi domain'iniz hep ilk satirda, rakipler anilma oranina gore.
        $ownRow = array_shift($rows);
        usort($rows, fn (array $a, array $b) => $b['rate'] <=> $a['rate'] ?: strcmp($a['domain'], $b['domain']));

        return [
            'answers' => count($answers),
            'providers' => $providers,
            'answered' => $answered,
            'rows' => $answers === [] ? [] : [$ownRow, ...$rows],
        ];
    }

    /** @param list<string> $hosts */
    private static function matches(array $hosts, string $domain): bool
    {
        foreach ($hosts as $host) {
            if (self::isSameSite($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    private static function isSameSite(string $host, string $domain): bool
    {
        return $domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain));
    }

    /**
     * Ayni siteyi iki kez gostermemek icin: tekrarlar ve listede ust
     * domain'i zaten bulunan alt domainler (blog.x.com, x.com varken) atilir.
     *
     * @param  list<string>  $hosts
     * @return list<string>
     */
    private static function dedupe(array $hosts): array
    {
        $kept = [];
        foreach ($hosts as $host) {
            if ($host === '' || in_array($host, $kept, true)) {
                continue;
            }
            foreach ($kept as $existing) {
                if (str_ends_with($host, '.' . $existing)) {
                    continue 2;
                }
            }
            $kept[] = $host;
        }

        return $kept;
    }
}
