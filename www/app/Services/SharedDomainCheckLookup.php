<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;

/**
 * Ayni domain string'ini (ornegin "example.com") farkli kullanicilar ayri
 * ayri takip edebilir (bkz. Domain.user_id, unique(domain, user_id)) - ama
 * robots.txt/AI-crawler erisimi, llms.txt, guvenlik header'lari, kanonik
 * host, CrUX ve Bing backlink verisi gibi domain-genelinde sabit gercekler
 * hangi kullanicinin sordugundan bagimsizdir. Bu servis, ayni domain
 * string'i icin baska bir kullanicinin Domain kaydina ait yeterince taze
 * bir DomainCheck bulup bu alanlarin tekrar taranmasini onlemeyi saglar.
 * Sitemap (kendi SitemapUrl senkronizasyonu icin URL listesine ihtiyac
 * duyar), GSC/GA4 (kullaniciya ozel OAuth/property) ve anahtar kelime
 * onerileri (kullaniciya ozel disleme listesi) bu paylasima dahil DEGILDIR
 * - her zaman taze calisir.
 */
final class SharedDomainCheckLookup
{
    private const REUSE_WINDOW_HOURS = 6;

    public function find(Domain $domain): ?DomainCheck
    {
        return DomainCheck::query()
            ->where('domain_id', '!=', $domain->id)
            ->whereHas('domain', fn ($q) => $q->where('domain', $domain->domain))
            ->where('created_at', '>=', now()->subHours(self::REUSE_WINDOW_HOURS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
