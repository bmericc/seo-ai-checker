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
 */
final class SitemapUrlSync
{
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
            foreach ($toCreate as $url) {
                $domain->sitemapUrls()->create([
                    'url' => $url,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
        }

        $domain->sitemapUrls()
            ->whereNotIn('url', $urls)
            ->whereNull('removed_at')
            ->update(['removed_at' => $now]);
    }
}
