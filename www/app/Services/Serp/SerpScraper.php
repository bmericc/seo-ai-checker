<?php

declare(strict_types=1);

namespace App\Services\Serp;

/**
 * CheckRunner'in ihtiyac duydugu tek sozlesme - Google'i dogrudan HTTP ile
 * kazan (GoogleSerpScraper) ya da ucuncu taraf bir SERP API'sini
 * (DataForSeoSerpScraper) cagiran implementasyonlar arasinda gecis
 * yapabilmek icin.
 */
interface SerpScraper
{
    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE = 'mobile';

    public function search(string $keyword, string $device = self::DEVICE_DESKTOP): SerpResult;

    /**
     * Mobil SERP'i gercekten mobil cihaz olarak sorgulayabiliyor mu? Dogrudan
     * kazima (GoogleSerpScraper) desteklemez - onda mobil sonuc hic
     * istenmemeli, yoksa masaustu sonucu "mobil" diye kaydedilir.
     */
    public function supportsMobile(): bool;
}
