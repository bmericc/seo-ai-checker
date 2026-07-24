<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bir domain string'ine (ornegin "example.com") ait, HANGI kullanicinin
 * bu domain'i takip ettiginden bagimsiz, tek ve ortak gercekler: WHOIS
 * bilgisi ve "Site Kontrolu"nun domain-genelinde sabit taraflari
 * (robots.txt AI-crawler erisimi, llms.txt, guvenlik header'lari, kanonik
 * host, CrUX). Ayni domain'i birden fazla Domain kaydi (farkli
 * kullanicilar) takip edebilir - hepsi bu TEK satiri paylasir (bkz.
 * Domain::fact()). Biri bu bilgiyi tazeledigginde (WHOIS yenile, Site
 * Kontrolu Yap) HERKESIN gordugu veri anlik olarak guncellenir - eskiden
 * (SharedDomainCheckLookup) her Domain kaydinin kendi DomainCheck
 * gecmisinde ayri ayri kopyalanan, birbirinden habersiz bir modeldi.
 *
 * GSC/GA4/Bing backlink (kullaniciya ozel OAuth/property), sitemap
 * (SitemapUrl senkronizasyonu icin domain'e bagli) ve anahtar kelime
 * onerileri (kullaniciya ozel disleme listesi) burada YOKTUR - onlar
 * DomainCheck uzerinde, Domain kaydina ozel kalmaya devam eder. DomainCheck
 * ayrica bu ortak alanlarin da bir KOPYASINI tutar (gecmis trend
 * grafikleri ve DomainCheckDrift icin) - o kopya "o an ne biliniyordu"
 * enstantanesidir, bu tablo ise "su an bilinen guncel gercek"tir.
 */
class DomainFact extends Model
{
    protected $fillable = [
        'domain',
        'whois_registrar',
        'whois_registered_at',
        'whois_expires_at',
        'whois_raw',
        'whois_error',
        'whois_checked_at',
        'ai_crawlers',
        'llms_txt',
        'security_headers',
        'canonical_host',
        'crux',
        'checked_at',
    ];

    protected $casts = [
        'whois_registered_at' => 'date',
        'whois_expires_at' => 'date',
        'whois_raw' => 'array',
        'whois_checked_at' => 'datetime',
        'ai_crawlers' => 'array',
        'llms_txt' => 'array',
        'security_headers' => 'array',
        'canonical_host' => 'array',
        'crux' => 'array',
        'checked_at' => 'datetime',
    ];

    public static function forDomain(string $domain): self
    {
        return self::query()->firstOrCreate(['domain' => $domain]);
    }

    /**
     * whois_registered_at bilinmiyorsa (WHOIS henuz cekilmediyse, ya da
     * kayit tarihini raporlamayan bir TLD icin bulunamadiysa) null doner.
     */
    public function whoisAgeInYears(): ?int
    {
        return $this->whois_registered_at === null
            ? null
            : (int) $this->whois_registered_at->diffInYears(now());
    }

    public function isStale(): bool
    {
        return $this->checked_at === null || $this->checked_at->lt(now()->subDays(7));
    }
}
