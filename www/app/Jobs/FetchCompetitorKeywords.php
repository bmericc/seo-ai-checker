<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DomainCompetitor;
use App\Services\Competitors\CompetitorSiteCrawler;
use App\Services\DataForSeo\DataForSeoException;
use App\Services\DataForSeo\RankedKeywordsFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Takip edilen bir rakibin one ciktigi anahtar kelimeleri ceker:
 * DataForSEO tanimliysa gercek siralama verisi (ranked_keywords), degilse
 * ya da DataForSEO hata verirse rakip sitesinin ucretsiz taramasi
 * (CompetitorSiteCrawler). Sonuc ham liste olarak
 * domain_competitors.keywords'e yazilir; takip edilen kelimelerle fark
 * sayfa acilirken hesaplanir (bkz. CompetitorKeywordGap), boylece bir
 * kelime eklendiginde bosluk listesi hemen guncellenir.
 *
 * Ucretli istek oldugu icin otomatik tekrar yoktur ($tries = 1).
 */
final class FetchCompetitorKeywords implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $competitorId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->competitorId;
    }

    public function handle(RankedKeywordsFetcher $rankedKeywords, CompetitorSiteCrawler $crawler): void
    {
        $competitor = DomainCompetitor::find($this->competitorId);
        if ($competitor === null || !$competitor->isTracked()) {
            return;
        }

        $error = null;

        if ($rankedKeywords->isConfigured()) {
            try {
                $keywords = $rankedKeywords->fetch($competitor->competitor_domain);

                // Kucuk/yeni sitelerde DataForSEO veritabani bos donebilir;
                // o zaman tarama en azindan konu sinyali verir.
                if ($keywords !== []) {
                    $this->store($competitor, $keywords, DomainCompetitor::KEYWORDS_DATAFORSEO, null);

                    return;
                }
            } catch (DataForSeoException $e) {
                $error = $e->getMessage();
            }
        }

        try {
            $keywords = $crawler->crawl($competitor->rootUrl());
        } catch (Throwable $e) {
            $this->store($competitor, $competitor->keywords ?? [], $competitor->keywords_source, $error ?? $e->getMessage());

            return;
        }

        if ($keywords === [] && $error === null) {
            $error = __('Rakip sitesinden anahtar kelime çıkarılamadı (site erişilemiyor ya da içerik bulunamadı).');
        }

        $this->store($competitor, $keywords, DomainCompetitor::KEYWORDS_CRAWL, $error);
    }

    /**
     * @param  list<array<string, mixed>>  $keywords
     */
    private function store(DomainCompetitor $competitor, array $keywords, ?string $source, ?string $error): void
    {
        $competitor->update([
            'keywords' => $keywords,
            'keywords_source' => $source,
            'keywords_fetched_at' => now(),
            'keywords_error' => $error,
        ]);
    }
}
