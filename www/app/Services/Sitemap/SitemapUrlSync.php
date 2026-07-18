<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

use App\Models\Domain;
use Illuminate\Support\Carbon;

/**
 * Bir SitemapChecker sonucundaki URL listesini domain'in sitemap_urls
 * gecmisiyle senkronize eder: hala listede olanlarin last_seen_at'i
 * guncellenir (ve daha once kaldirilmis isaretliyse removed_at temizlenir),
 * yeni gorulenler olusturulur, artik listede olmayanlar removed_at ile
 * isaretlenir (silinmez - gecmis korunur).
 *
 * Yeni olusturulan bir URL icin, ayni domain string'ini takip eden baska
 * bir kullanicinin Lighthouse/on-page tarama sonucu varsa (bkz.
 * SharedSitemapUrlLookup) o sonuc dogrudan kopyalanir - tazeligi ne olursa
 * olsun, hicbir sey olmamasindan iyidir; zaten cok eskiyse mevcut
 * "hic taranmamis/en eski taranmis once" sirlama mantigi onu yine de
 * yeniden taramaya aday yapar.
 */
final class SitemapUrlSync
{
    public function __construct(
        private readonly SharedSitemapUrlLookup $sharedLookup,
    ) {
    }

    /**
     * @param  string[]  $urls
     */
    public function sync(Domain $domain, array $urls): void
    {
        $now = Carbon::now();
        $urls = array_values(array_unique($urls));

        if ($urls !== []) {
            $existing = $domain->sitemapUrls()
                ->whereIn('url', $urls)
                ->pluck('url')
                ->all();

            $toUpdate = array_intersect($urls, $existing);
            if ($toUpdate !== []) {
                $domain->sitemapUrls()
                    ->whereIn('url', $toUpdate)
                    ->update(['last_seen_at' => $now, 'removed_at' => null]);
            }

            $toCreate = array_diff($urls, $existing);
            if ($toCreate !== []) {
                $siblings = $this->sharedLookup->siblingsByUrl($domain, $toCreate);

                foreach ($toCreate as $url) {
                    $sibling = $siblings->get($url);

                    $domain->sitemapUrls()->create([
                        'url' => $url,
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                        ...($sibling !== null ? $this->sharedLookup->lighthouseFields($sibling) : []),
                        ...($sibling !== null ? $this->sharedLookup->onPageFields($sibling) : []),
                    ]);
                }
            }
        }

        $domain->sitemapUrls()
            ->whereNotIn('url', $urls)
            ->whereNull('removed_at')
            ->update(['removed_at' => $now]);
    }
}
