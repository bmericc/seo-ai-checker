<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BacklinkSnapshot;
use App\Models\CrawlGraphSnapshot;
use App\Models\Domain;
use Illuminate\Support\Collection;

/**
 * Domain sayfasindaki "Backlink" sekmesinin verisi: son basarili cekim,
 * ondan onceki cekim (degisim icin), trend noktalari ve son cekim
 * basarisizsa o satir. Veri FetchDomainBacklinks ile kuyruktan gelir.
 * Referring domain listeleri DataForSEO + CrawlGraph + Bing birlesik halidir.
 * Bing satirlari son site kontrolunden (domain_checks.bing_backlinks) gelir.
 */
final class BacklinkOverview
{
    /** Trend grafigine giren en fazla basarili cekim. */
    private const HISTORY_POINTS = 26;

    /**
     * @return array{snapshot: ?BacklinkSnapshot, previous: ?BacklinkSnapshot, failedLatest: ?BacklinkSnapshot, history: Collection<int, BacklinkSnapshot>, isRefreshing: bool, crawlGraph: ?CrawlGraphSnapshot, crawlGraphConfigured: bool, bingDomainCount: ?int, merged: array{domains: list<array<string, mixed>>, new: list<array<string, mixed>>, lost: list<array<string, mixed>>}}
     */
    public static function forDomain(Domain $domain): array
    {
        $latest = $domain->backlinkSnapshots()->latest('fetched_at')->latest('id')->first();
        $snapshot = $latest?->isSuccessful()
            ? $latest
            : $domain->backlinkSnapshots()->whereNull('error')->latest('fetched_at')->latest('id')->first();

        $previous = $snapshot === null ? null : $domain->backlinkSnapshots()
            ->whereNull('error')
            ->where('fetched_at', '<', $snapshot->fetched_at)
            ->latest('fetched_at')
            ->first();

        $history = $domain->backlinkSnapshots()
            ->whereNull('error')
            ->latest('fetched_at')
            ->limit(self::HISTORY_POINTS)
            ->get(['fetched_at', 'backlinks', 'referring_domains', 'rank'])
            ->reverse()
            ->values();

        $crawlGraph = $domain->crawlGraphSnapshots()->whereNull('error')->latest('fetched_at')->first();

        $bingData = $domain->latestDomainCheck?->bing_backlinks ?? [];
        $bingDomains = is_array($bingData['referring_domains'] ?? null) ? $bingData['referring_domains'] : null;

        return [
            'snapshot' => $snapshot,
            'previous' => $previous,
            'failedLatest' => $latest !== null && !$latest->isSuccessful() ? $latest : null,
            'history' => $history,
            'isRefreshing' => $domain->backlinks_requested_at !== null,
            // Common Crawl (CrawlGraph): en yeni basarili arsiv satiri.
            'crawlGraph' => $crawlGraph,
            // Iki kaynagin mukerrersiz birlesik listeleri (bkz. BacklinkMerger).
            'merged' => [
                'domains' => BacklinkMerger::merge($snapshot?->top_referring_domains, $crawlGraph?->linking_domains, $bingDomains),
                'new' => BacklinkMerger::merge($snapshot?->new_referring_domains, $crawlGraph?->added_domains),
                'lost' => BacklinkMerger::merge($snapshot?->lost_referring_domains, $crawlGraph?->removed_domains),
            ],
            'crawlGraphConfigured' => (string) config('seo.crawlgraph.api_key') !== '',
            // Bing Webmaster: kullanicinin dogruladigi sitelerde, site kontroluyle gelir.
            'bingDomainCount' => $bingDomains === null ? null : count($bingDomains),
        ];
    }
}
