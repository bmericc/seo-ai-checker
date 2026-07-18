<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

use App\Models\Domain;
use App\Models\SitemapUrl;
use Illuminate\Support\Collection;

/**
 * Ayni domain string'ini farkli kullanicilar ayri Domain kayitlariyla takip
 * edebilir (bkz. SharedDomainCheckLookup) - sitemap'ten kesfedilen sayfalar
 * ve onlarin Lighthouse/on-page tarama sonuclari da URL bazinda ayni sekilde
 * paylasilir: bir kullanici zaten bir sayfayi taze taradiysa, digeri icin
 * PSI/on-page taramasi tekrarlanmaz, mevcut sonuc kopyalanir.
 */
final class SharedSitemapUrlLookup
{
    public const REUSE_WINDOW_HOURS = 6;

    /**
     * @param  string[]  $urls
     * @return Collection<string, SitemapUrl>
     */
    public function siblingsByUrl(Domain $domain, array $urls): Collection
    {
        if ($urls === []) {
            return collect();
        }

        return SitemapUrl::query()
            ->where('domain_id', '!=', $domain->id)
            ->whereIn('url', $urls)
            ->whereHas('domain', fn ($q) => $q->where('domain', $domain->domain))
            ->orderBy('updated_at')
            ->get()
            ->keyBy('url');
    }

    public function isLighthouseFresh(?SitemapUrl $sibling): bool
    {
        return $sibling?->lighthouse_checked_at !== null
            && $sibling->lighthouse_checked_at->greaterThanOrEqualTo(now()->subHours(self::REUSE_WINDOW_HOURS));
    }

    public function isOnPageFresh(?SitemapUrl $sibling): bool
    {
        return $sibling?->onpage_checked_at !== null
            && $sibling->onpage_checked_at->greaterThanOrEqualTo(now()->subHours(self::REUSE_WINDOW_HOURS));
    }

    /**
     * @return array<string, mixed>
     */
    public function lighthouseFields(SitemapUrl $sibling): array
    {
        return [
            'lighthouse_performance' => $sibling->lighthouse_performance,
            'lighthouse_seo' => $sibling->lighthouse_seo,
            'lighthouse_accessibility' => $sibling->lighthouse_accessibility,
            'lighthouse_best_practices' => $sibling->lighthouse_best_practices,
            'lighthouse_raw' => $sibling->lighthouse_raw,
            'lighthouse_error' => $sibling->lighthouse_error,
            'lighthouse_checked_at' => $sibling->lighthouse_checked_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function onPageFields(SitemapUrl $sibling): array
    {
        return [
            'onpage_data' => $sibling->onpage_data,
            'onpage_error' => $sibling->onpage_error,
            'onpage_checked_at' => $sibling->onpage_checked_at,
        ];
    }
}
