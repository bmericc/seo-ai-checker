<?php

declare(strict_types=1);

namespace App\Services\Keywords;

use App\Jobs\CrawlSiteKeywordCandidates;
use App\Models\Domain;

/**
 * Site geneli oneri listesini (domains.site_keyword_suggestions) ve arka
 * plan taramasinin ilerlemesini (domains.site_keyword_scan) yoneten ince
 * katman: DomainCheckRunner taramayi baslatir, CrawlSiteKeywordCandidates
 * her dilimden sonra listeyi yeniler.
 */
final class SiteKeywordSuggestionRefresher
{
    public function __construct(private readonly SiteKeywordAggregator $aggregator)
    {
    }

    /**
     * Yeni bir tarama turu baslatir: ilerleme sayaci sifirlanir (mevcut
     * sayfa verisi korunur - yalnizca taranmamis/eskimis URL'ler yeniden
     * cekilir), liste ana sayfa onerileriyle hemen yenilenir ve is kuyruga
     * birakilir.
     */
    public function start(Domain $domain): void
    {
        $domain->update([
            'site_keyword_scan' => [
                'status' => 'running',
                'scanned' => 0,
                'started_at' => now()->toIso8601String(),
                'finished_at' => null,
            ],
        ]);

        $this->refresh($domain, 'running');

        CrawlSiteKeywordCandidates::dispatch($domain->id);
    }

    public function refresh(Domain $domain, string $status): void
    {
        $domain->load('latestDomainCheck');

        $homepage = array_map(
            static fn (array $s) => new KeywordSuggestion((string) $s['phrase'], (float) $s['score']),
            $domain->latestDomainCheck?->suggested_keywords ?? [],
        );

        $rootUrls = $this->rootUrls($domain);

        $pages = $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->whereNotNull('keyword_phrases')
            ->get(['url', 'keyword_phrases'])
            ->reject(fn ($u) => in_array(rtrim($u->url, '/'), $rootUrls, true))
            ->mapWithKeys(fn ($u) => [$u->url => $u->keyword_phrases ?? []])
            ->all();

        $exclude = [
            ...$domain->keywords()->pluck('keyword')->all(),
            ...($domain->dismissed_keyword_suggestions ?? []),
        ];

        $suggestions = $this->aggregator->aggregate($homepage, $pages, $exclude);

        $scan = $domain->site_keyword_scan ?? [];
        $scan['status'] = $status;
        $scan['total'] = $domain->sitemapUrls()->whereNull('removed_at')->count();
        $scan['pages_with_data'] = count(array_filter($pages, static fn ($p) => is_array($p) && $p !== []));
        $scan['finished_at'] = $status === 'done' ? now()->toIso8601String() : null;

        $domain->update([
            'site_keyword_suggestions' => array_map(static fn (KeywordSuggestion $s) => $s->toArray(), $suggestions),
            'site_keyword_scan' => $scan,
        ]);
    }

    /**
     * Ana sayfa zaten $homepage olarak ayrica eklendigi icin sitemap'te de
     * varsa iki kez sayilmasin.
     *
     * @return string[]
     */
    private function rootUrls(Domain $domain): array
    {
        $hosts = [$domain->domain, 'www.'.$domain->domain];
        $canonical = $domain->latestDomainCheck?->canonical_host['canonical_host'] ?? null;
        if (is_string($canonical) && $canonical !== '') {
            $hosts[] = $canonical;
        }

        $urls = [];
        foreach (array_unique($hosts) as $host) {
            $urls[] = 'https://'.$host;
            $urls[] = 'http://'.$host;
        }

        return $urls;
    }
}
