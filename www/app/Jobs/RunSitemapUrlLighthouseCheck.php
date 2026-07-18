<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SitemapUrl;
use App\Services\Lighthouse\PageSpeedInsightsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Bir sitemap URL'i icin Google PageSpeed Insights (Lighthouse) denetimini
 * calistirip sonucu SitemapUrl kaydina yazar. Kuyruk uzerinden calisir -
 * PSI cagrisi 15-40 saniye surebildiginden bunu web istegi icinde
 * calistirmak sayfayi kilitler; bu yuzden domain kontrolu/arayuz bu isi
 * dogrudan tetiklemez, sadece kuyruga is birakir.
 */
final class RunSitemapUrlLighthouseCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $sitemapUrlId)
    {
    }

    public function handle(PageSpeedInsightsClient $client): void
    {
        $sitemapUrl = SitemapUrl::find($this->sitemapUrlId);

        if ($sitemapUrl === null || $sitemapUrl->removed_at !== null) {
            return;
        }

        $result = $client->analyze($sitemapUrl->url);

        $sitemapUrl->update([
            'lighthouse_performance' => $result->performanceScore,
            'lighthouse_seo' => $result->seoScore,
            'lighthouse_accessibility' => $result->accessibilityScore,
            'lighthouse_best_practices' => $result->bestPracticesScore,
            'lighthouse_raw' => $result->raw,
            'lighthouse_error' => $result->error,
            'lighthouse_checked_at' => now(),
        ]);
    }
}
