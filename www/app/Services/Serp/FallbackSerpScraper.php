<?php

declare(strict_types=1);

namespace App\Services\Serp;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * SERP_PROVIDER=fallback modu: once ucretsiz dogrudan kazima (primary,
 * GoogleSerpScraper) denenir; sonuc engellenmisse (CAPTCHA / "unusual
 * traffic" / HTTP hatasi) ayni sorgu ucretli saglayiciya (fallback,
 * DataForSeoSerpScraper) gider. Boylece ucret yalnizca engel olunca kesilir.
 *
 * Primary engellendiginde bir "soguma" suresi baslar (cooldownMinutes):
 * bu surede primary hic denenmez - engellenmis IP'yi daha cok zorlamamak
 * ve her sorguda throttle bekleme suresi + basarisiz istek maliyetini
 * odememek icin. 0 verilirse soguma kapalidir.
 *
 * Mobil: primary mobil desteklemiyorsa (GoogleSerpScraper) supportsMobile()
 * false doner ve CheckRunner mobil sorguyu hic yapmaz - aksi halde her
 * kontrolde mobil sorgu ucretli saglayiciya gider, bu modun amaci bozulur.
 */
final class FallbackSerpScraper implements SerpScraper
{
    private const COOLDOWN_CACHE_KEY = 'serp:primary-blocked-until';

    public function __construct(
        private readonly SerpScraper $primary,
        private readonly SerpScraper $fallback,
        private readonly ?Cache $cache = null,
        private readonly int $cooldownMinutes = 30,
    ) {
    }

    public function supportsMobile(): bool
    {
        return $this->primary->supportsMobile();
    }

    public function search(string $keyword, string $device = self::DEVICE_DESKTOP): SerpResult
    {
        if ($this->inCooldown()) {
            return $this->searchFallback($keyword, $device, 'Google kısa süre önce engelledi (soğuma süresi)');
        }

        try {
            $result = $this->primary->search($keyword, $device);
        } catch (GuzzleException $e) {
            $result = new SerpResult(
                keyword: $keyword,
                organicResults: [],
                aiOverview: new AiOverviewResult(present: false),
                blocked: true,
                blockReason: sprintf('Google isteğine ulaşılamadı: %s', $e->getMessage()),
            );
        }

        if (!$result->blocked) {
            return $result;
        }

        $this->startCooldown();

        return $this->searchFallback($keyword, $device, (string) $result->blockReason);
    }

    private function searchFallback(string $keyword, string $device, string $primaryReason): SerpResult
    {
        $fallbackReason = null;

        try {
            $result = $this->fallback->search($keyword, $device);
        } catch (GuzzleException $e) {
            $result = null;
            $fallbackReason = $e->getMessage();
        }

        if ($result !== null && !$result->blocked) {
            return $result;
        }

        return new SerpResult(
            keyword: $keyword,
            organicResults: [],
            aiOverview: new AiOverviewResult(present: false),
            blocked: true,
            blockReason: sprintf(
                '%s Yedek sağlayıcı (DataForSEO) da başarısız oldu: %s',
                rtrim($primaryReason, '. ') . '.',
                $result?->blockReason ?? $fallbackReason ?? 'bilinmeyen hata',
            ),
        );
    }

    private function inCooldown(): bool
    {
        if ($this->cache === null || $this->cooldownMinutes <= 0) {
            return false;
        }

        $until = $this->cache->get(self::COOLDOWN_CACHE_KEY);

        return is_int($until) && $until > time();
    }

    private function startCooldown(): void
    {
        if ($this->cache === null || $this->cooldownMinutes <= 0) {
            return;
        }

        $until = time() + $this->cooldownMinutes * 60;
        $this->cache->put(self::COOLDOWN_CACHE_KEY, $until, $this->cooldownMinutes * 60);
    }
}
