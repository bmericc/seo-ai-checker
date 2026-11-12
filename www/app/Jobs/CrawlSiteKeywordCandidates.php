<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Models\SitemapUrl;
use App\Services\Keywords\KeywordSuggester;
use App\Services\Keywords\SiteKeywordSuggestionRefresher;
use App\Services\Sitemap\SharedSitemapUrlLookup;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Domain'in sitemap URL'lerini arka planda dilim dilim tarayip her sayfanin
 * anahtar kelime adaylarini sitemap_urls.keyword_phrases'e yazar; her
 * dilimden sonra site geneli oneri listesini yeniden hesaplar. Kullanici
 * taramanin bitmesini beklemez - domain sayfasi o ana kadarki en guncel
 * listeyi gosterir ve tarama surerken kendini tazeler.
 *
 * Her calisma en fazla BATCH_SIZE URL ve TIME_BUDGET_SECONDS ile sinirlidir
 * (database queue'nun retry_after'i 90 sn - asilirsa ayni is ikinci kez
 * baslatilir). Kalan URL varsa is kendini gecikmeli olarak yeniden
 * kuyruga koyar.
 *
 * ShouldBeUniqueUntilProcessing kuyrukta ayni domain icin tek bir bekleyen
 * is birakir; calisma sirasinda alinan Cache lock'u da ayni anda iki
 * zincirin calismasini engeller (ikinci gelen sessizce cikar, ilki devam
 * eder).
 */
final class CrawlSiteKeywordCandidates implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const BATCH_SIZE = 20;

    /** Bir domain icin taranacak en fazla sayfa (tek tarama turunda). */
    public const MAX_PAGES = 500;

    /** Bu kadar eski keyword_phrases yeniden taranir. */
    public const STALE_AFTER_DAYS = 7;

    public const PHRASES_PER_PAGE = 30;

    private const TIME_BUDGET_SECONDS = 60;

    private const NEXT_BATCH_DELAY_SECONDS = 10;

    /** Ayni siteye ardisik istekler arasinda bekleme (mikrosaniye). */
    private const REQUEST_GAP_MICROSECONDS = 500_000;

    public int $tries = 1;

    public int $timeout = 85;

    public function __construct(public readonly int $domainId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->domainId;
    }

    public function handle(
        Client $client,
        KeywordSuggester $suggester,
        SharedSitemapUrlLookup $sharedLookup,
        SiteKeywordSuggestionRefresher $refresher,
    ): void {
        $domain = Domain::find($this->domainId);
        if ($domain === null) {
            return;
        }

        $lock = Cache::lock('site-keyword-scan:'.$this->domainId, $this->timeout + 5);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->processBatch($domain, $client, $suggester, $sharedLookup);

            $remaining = self::pendingQuery($domain)->exists();
            $scanned = (int) ($domain->site_keyword_scan['scanned'] ?? 0);
            $done = ! $remaining || $scanned >= self::MAX_PAGES;

            $refresher->refresh($domain, $done ? 'done' : 'running');

            if (! $done) {
                self::dispatch($this->domainId)->delay(now()->addSeconds(self::NEXT_BATCH_DELAY_SECONDS));
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Taranmayi bekleyen aktif URL'ler: hic taranmamislar once, sonra
     * STALE_AFTER_DAYS'den eskiler (en eski once).
     */
    public static function pendingQuery(Domain $domain)
    {
        return $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->where(function ($q) {
                $q->whereNull('keyword_phrases_checked_at')
                    ->orWhere('keyword_phrases_checked_at', '<', now()->subDays(self::STALE_AFTER_DAYS));
            });
    }

    private function processBatch(Domain $domain, Client $client, KeywordSuggester $suggester, SharedSitemapUrlLookup $sharedLookup): void
    {
        $urls = self::pendingQuery($domain)
            ->orderByRaw('keyword_phrases_checked_at IS NOT NULL')
            ->orderBy('keyword_phrases_checked_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($urls->isEmpty()) {
            return;
        }

        $siblings = $sharedLookup->siblingsByUrl($domain, $urls->pluck('url')->all());
        $started = microtime(true);
        $processed = 0;
        $fetched = false;

        foreach ($urls as $url) {
            if (microtime(true) - $started > self::TIME_BUDGET_SECONDS) {
                break;
            }

            $sibling = $siblings->get($url->url);
            if ($sharedLookup->isKeywordPhrasesFresh($sibling)) {
                $url->update($sharedLookup->keywordPhraseFields($sibling));
                $processed++;
                continue;
            }

            if ($fetched) {
                usleep(self::REQUEST_GAP_MICROSECONDS);
            }
            $fetched = true;

            $url->update([
                'keyword_phrases' => $this->phrasesFor($url, $client, $suggester),
                'keyword_phrases_checked_at' => now(),
            ]);
            $processed++;
        }

        $scan = $domain->site_keyword_scan ?? [];
        $scan['scanned'] = (int) ($scan['scanned'] ?? 0) + $processed;
        $domain->update(['site_keyword_scan' => $scan]);
    }

    /**
     * @return array<int, array{phrase: string, score: float}>
     */
    private function phrasesFor(SitemapUrl $url, Client $client, KeywordSuggester $suggester): array
    {
        try {
            $response = $client->get($url->url);
        } catch (GuzzleException) {
            return [];
        }

        if ($response->getStatusCode() >= 400) {
            return [];
        }

        // WordPress ek sayfalari (ornek: /logo-2/) cogu zaman dogrudan resim
        // dosyasina yonlenir; redirect izlendigi icin govde JPEG/PDF olur.
        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html')) {
            return [];
        }

        return self::phrasesFromHtml($suggester, (string) $response->getBody(), $url->url);
    }

    /**
     * RunSitemapUrlOnPageCheck de ayni formati kullanir.
     *
     * @return array<int, array{phrase: string, score: float}>
     */
    public static function phrasesFromHtml(KeywordSuggester $suggester, string $html, string $url): array
    {
        if (! self::looksLikeHtml($html)) {
            return [];
        }

        $suggestions = $suggester->suggestFromHtml($html, $url, limit: self::PHRASES_PER_PAGE, stripChrome: true);

        return array_map(
            static fn ($s) => ['phrase' => $s->phrase, 'score' => round($s->score, 4)],
            $suggestions,
        );
    }

    /**
     * Content-Type'a guvenilemeyen yollar (OnPageSeoAnalyzer) icin govde
     * koklamasi: NUL bayti iceren ya da hic etiket barindirmayan govde
     * (resim, PDF vb.) HTML degildir - ikili veri "ÿøÿà jfif" gibi cop
     * ifadeler uretir.
     */
    private static function looksLikeHtml(string $body): bool
    {
        if ($body === '' || str_contains(substr($body, 0, 4096), "\0")) {
            return false;
        }

        return preg_match('/<(?:!doctype|html|head|body|title|meta|div|p|h[1-6])\b/i', substr($body, 0, 65536)) === 1;
    }
}
