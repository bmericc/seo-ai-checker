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
    public function search(string $keyword): SerpResult;
}
