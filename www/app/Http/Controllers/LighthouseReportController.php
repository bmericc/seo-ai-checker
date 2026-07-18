<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunSitemapUrlLighthouseCheck;
use App\Models\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sitemap'ten kesfedilen sayfalarin toplu Lighthouse (PSI) taramasini ve
 * raporunu yonetir. Tarama, kuyruk uzerinden (RunSitemapUrlLighthouseCheck)
 * arka planda calisir - bu controller yalnizca isleri kuyruga birakir,
 * PSI'yi kendisi cagirmaz.
 */
class LighthouseReportController extends Controller
{
    private const MAX_URLS_PER_BATCH = 50;

    public function show(Request $request, Domain $domain): View
    {
        $this->ensureCanAccessDomain($request, $domain);

        $sitemapUrls = $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderByDesc('lighthouse_checked_at')
            ->get();

        $checked = $sitemapUrls->filter(fn ($u) => $u->isLighthouseChecked() && $u->lighthouse_error === null);

        $summary = [
            'total' => $sitemapUrls->count(),
            'checked' => $checked->count(),
            'failed' => $sitemapUrls->filter(fn ($u) => $u->lighthouse_error !== null)->count(),
            'pending' => $sitemapUrls->filter(fn ($u) => !$u->isLighthouseChecked())->count(),
            'avg_performance' => round($checked->avg('lighthouse_performance') ?? 0),
            'avg_seo' => round($checked->avg('lighthouse_seo') ?? 0),
            'avg_accessibility' => round($checked->avg('lighthouse_accessibility') ?? 0),
            'avg_best_practices' => round($checked->avg('lighthouse_best_practices') ?? 0),
        ];

        return view('domains.lighthouse-report', [
            'domain' => $domain,
            'sitemapUrls' => $sitemapUrls,
            'summary' => $summary,
            'maxUrlsPerBatch' => self::MAX_URLS_PER_BATCH,
        ]);
    }

    public function start(Request $request, Domain $domain): RedirectResponse
    {
        $this->ensureCanAccessDomain($request, $domain);

        $urls = $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderByRaw('lighthouse_checked_at IS NOT NULL')
            ->orderBy('lighthouse_checked_at')
            ->limit(self::MAX_URLS_PER_BATCH)
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
}
