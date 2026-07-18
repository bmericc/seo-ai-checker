<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunSitemapUrlLighthouseCheck;
use App\Jobs\RunSitemapUrlOnPageCheck;
use App\Models\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sitemap'ten kesfedilen sayfalarin toplu Lighthouse (PSI) ve on-page SEO
 * taramasini ve raporunu yonetir. Her ikisi de kuyruk uzerinden
 * (RunSitemapUrlLighthouseCheck / RunSitemapUrlOnPageCheck) arka planda
 * calisir - bu controller yalnizca isleri kuyruga birakir, PSI'yi veya
 * sayfa HTML'ini kendisi cagirmaz.
 */
class LighthouseReportController extends Controller
{
    private const MAX_LIGHTHOUSE_URLS_PER_BATCH = 50;

    // On-page analiz kendi HTTP+DOM taramamiz, PSI kadar yavas/kotali degil.
    private const MAX_ONPAGE_URLS_PER_BATCH = 200;

    public function show(Request $request, Domain $domain): View
    {
        $this->ensureCanAccessDomain($request, $domain);

        $sitemapUrls = $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderByDesc('lighthouse_checked_at')
            ->get();

        $lighthouseChecked = $sitemapUrls->filter(fn ($u) => $u->isLighthouseChecked() && $u->lighthouse_error === null);

        $lighthouseSummary = [
            'total' => $sitemapUrls->count(),
            'checked' => $lighthouseChecked->count(),
            'failed' => $sitemapUrls->filter(fn ($u) => $u->lighthouse_error !== null)->count(),
            'pending' => $sitemapUrls->filter(fn ($u) => !$u->isLighthouseChecked())->count(),
            'avg_performance' => round($lighthouseChecked->avg('lighthouse_performance') ?? 0),
            'avg_seo' => round($lighthouseChecked->avg('lighthouse_seo') ?? 0),
            'avg_accessibility' => round($lighthouseChecked->avg('lighthouse_accessibility') ?? 0),
            'avg_best_practices' => round($lighthouseChecked->avg('lighthouse_best_practices') ?? 0),
        ];

        $onPageChecked = $sitemapUrls->filter(fn ($u) => $u->isOnPageChecked() && $u->onpage_error === null);

        $onPageSummary = [
            'total' => $sitemapUrls->count(),
            'checked' => $onPageChecked->count(),
            'failed' => $sitemapUrls->filter(fn ($u) => $u->onpage_error !== null)->count(),
            'pending' => $sitemapUrls->filter(fn ($u) => !$u->isOnPageChecked())->count(),
            'missing_canonical' => $onPageChecked->filter(fn ($u) => ($u->onpage_data['canonical_status'] ?? null) === 'missing')->count(),
            'wrong_h1_count' => $onPageChecked->filter(fn ($u) => ($u->onpage_data['h1_count'] ?? 1) !== 1)->count(),
            'heading_skips' => $onPageChecked->filter(fn ($u) => $u->onpage_data['heading_hierarchy_skip'] ?? false)->count(),
            'missing_og' => $onPageChecked->filter(fn ($u) => collect($u->onpage_data['og_tags'] ?? [])->filter()->isEmpty())->count(),
        ];

        return view('domains.lighthouse-report', [
            'domain' => $domain,
            'sitemapUrls' => $sitemapUrls,
            'lighthouseSummary' => $lighthouseSummary,
            'onPageSummary' => $onPageSummary,
            'maxLighthouseUrlsPerBatch' => self::MAX_LIGHTHOUSE_URLS_PER_BATCH,
            'maxOnPageUrlsPerBatch' => self::MAX_ONPAGE_URLS_PER_BATCH,
        ]);
    }

    public function start(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $urls = $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderByRaw('lighthouse_checked_at IS NOT NULL')
            ->orderBy('lighthouse_checked_at')
            ->limit(self::MAX_LIGHTHOUSE_URLS_PER_BATCH)
            ->get();

        foreach ($urls as $url) {
            RunSitemapUrlLighthouseCheck::dispatch($url->id);
        }

        return redirect()
            ->route('domains.lighthouse-report', $domain)
            ->with('flash', [
                'type' => 'success',
                'message' => $urls->isEmpty()
                    ? 'Taranacak yeni sayfa yok.'
                    : sprintf('%d sayfa Lighthouse taraması için kuyruğa eklendi.', $urls->count()),
            ]);
    }

    public function startOnPage(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $urls = $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderByRaw('onpage_checked_at IS NOT NULL')
            ->orderBy('onpage_checked_at')
            ->limit(self::MAX_ONPAGE_URLS_PER_BATCH)
            ->get();

        foreach ($urls as $url) {
            RunSitemapUrlOnPageCheck::dispatch($url->id);
        }

        return redirect()
            ->route('domains.lighthouse-report', $domain)
            ->with('flash', [
                'type' => 'success',
                'message' => $urls->isEmpty()
                    ? 'Taranacak yeni sayfa yok.'
                    : sprintf('%d sayfa on-page SEO analizi için kuyruğa eklendi.', $urls->count()),
            ]);
    }
}
