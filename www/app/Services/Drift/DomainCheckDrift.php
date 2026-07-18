<?php

declare(strict_types=1);

namespace App\Services\Drift;

use App\Models\DomainCheck;

/**
 * Iki ardisik DomainCheck kaydini (en son ve bir onceki "Site Kontrolu
 * Yap" calistirmasi) karsilastirip SEO-kritik alanlarda ne degisti diye
 * raporlar. Yeni bir tablo/gecmis gerektirmez - DomainCheck zaten her
 * calistirmada yeni bir satir olusturuyor (guncelleme degil), bu yuzden
 * "son iki kayit" karsilastirmasi mevcut veriyle dogrudan calisir.
 */
final class DomainCheckDrift
{
    private const CRUX_RATING_ORDER = ['good' => 0, 'needs_improvement' => 1, 'poor' => 2];

    /**
     * @return string[] insan-okunabilir degisiklik mesajlari
     */
    public function diff(DomainCheck $current, DomainCheck $previous): array
    {
        // JSON sutunlari eski (bu alan henuz eklenmeden yapilmis) kayitlarda
        // null olabilir; $col['key'] ?? default, $col'un kendisi null iken
        // PHP 8'in "null uzerinde array offset erisimi" hatasini bastirmaz -
        // bu yuzden her sutunu once diziye normalize ediyoruz.
        $curAiCrawlers = $current->ai_crawlers ?? [];
        $prevAiCrawlers = $previous->ai_crawlers ?? [];
        $curSitemap = $current->sitemap ?? [];
        $prevSitemap = $previous->sitemap ?? [];
        $curLlmsTxt = $current->llms_txt ?? [];
        $prevLlmsTxt = $previous->llms_txt ?? [];
        $curSecurityHeaders = $current->security_headers ?? [];
        $prevSecurityHeaders = $previous->security_headers ?? [];
        $curCanonicalHost = $current->canonical_host ?? [];
        $prevCanonicalHost = $previous->canonical_host ?? [];
        $curCrux = $current->crux ?? [];
        $prevCrux = $previous->crux ?? [];

        return [
            ...$this->aiCrawlerChanges($curAiCrawlers, $prevAiCrawlers),
            ...$this->sitemapChanges($curSitemap, $prevSitemap),
            ...$this->llmsTxtChanges($curLlmsTxt, $prevLlmsTxt),
            ...$this->securityHeaderChanges($curSecurityHeaders, $prevSecurityHeaders),
            ...$this->canonicalHostChanges($curCanonicalHost, $prevCanonicalHost),
            ...$this->cruxChanges($curCrux, $prevCrux),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return string[]
     */
    private function aiCrawlerChanges(array $current, array $previous): array
    {
        $curBlocked = collect($current['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed'])->keys();
        $prevBlocked = collect($previous['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed'])->keys();

        $newlyBlocked = $curBlocked->diff($prevBlocked);
        $newlyAllowed = $prevBlocked->diff($curBlocked);

        $changes = [];
        if ($newlyBlocked->isNotEmpty()) {
            $changes[] = sprintf("Yeni engellenen AI crawler'lar: %s", $newlyBlocked->implode(', '));
        }
        if ($newlyAllowed->isNotEmpty()) {
            $changes[] = sprintf("Artık engellenmeyen AI crawler'lar: %s", $newlyAllowed->implode(', '));
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return string[]
     */
    private function sitemapChanges(array $current, array $previous): array
    {
        $curFound = $current['found'] ?? false;
        $prevFound = $previous['found'] ?? false;

        if ($curFound !== $prevFound) {
            return [$curFound ? 'Sitemap artık bulunuyor' : 'Sitemap artık bulunamıyor'];
        }

        if ($curFound) {
            $curValid = $current['is_valid_xml'] ?? false;
            $prevValid = $previous['is_valid_xml'] ?? false;
            if ($curValid !== $prevValid) {
                return [$curValid ? 'Sitemap artık geçerli XML' : 'Sitemap artık geçersiz XML'];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return string[]
     */
    private function llmsTxtChanges(array $current, array $previous): array
    {
        $curFound = $current['found'] ?? false;
        $prevFound = $previous['found'] ?? false;

        return $curFound !== $prevFound ? [$curFound ? 'llms.txt artık var' : 'llms.txt artık yok'] : [];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return string[]
     */
    private function securityHeaderChanges(array $current, array $previous): array
    {
        $changes = [];

        $curHeaders = collect($current['headers'] ?? [])->filter();
        $prevHeaders = collect($previous['headers'] ?? [])->filter();

        $removed = $prevHeaders->keys()->diff($curHeaders->keys());
        $added = $curHeaders->keys()->diff($prevHeaders->keys());

        if ($removed->isNotEmpty()) {
            $changes[] = sprintf("Kaldırılan güvenlik header'ları: %s", $removed->implode(', '));
        }
        if ($added->isNotEmpty()) {
            $changes[] = sprintf("Eklenen güvenlik header'ları: %s", $added->implode(', '));
        }

        $curHttps = $current['http_redirects_to_https'] ?? false;
        $prevHttps = $previous['http_redirects_to_https'] ?? false;
        if ($curHttps !== $prevHttps) {
            $changes[] = $curHttps ? "HTTP artık HTTPS'e yönlendiriliyor" : "HTTP artık HTTPS'e yönlendirilmiyor";
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return string[]
     */
    private function canonicalHostChanges(array $current, array $previous): array
    {
        $curHost = $current['canonical_host'] ?? null;
        $prevHost = $previous['canonical_host'] ?? null;

        if ($curHost !== null && $prevHost !== null && $curHost !== $prevHost) {
            return [sprintf('Kanonik host değişti: %s → %s', $prevHost, $curHost)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return string[]
     */
    private function cruxChanges(array $current, array $previous): array
    {
        $curMetrics = $current['metrics'] ?? [];
        $prevMetrics = $previous['metrics'] ?? [];

        $changes = [];
        foreach ($curMetrics as $key => $metric) {
            $prevRating = $prevMetrics[$key]['rating'] ?? null;
            $curRating = $metric['rating'] ?? null;
            if ($prevRating === null || $curRating === null || $prevRating === $curRating) {
                continue;
            }

            $prevScore = self::CRUX_RATING_ORDER[$prevRating] ?? null;
            $curScore = self::CRUX_RATING_ORDER[$curRating] ?? null;
            if ($prevScore === null || $curScore === null) {
                continue;
            }

            $label = $metric['label'] ?? $key;
            $changes[] = $curScore > $prevScore
                ? sprintf('CrUX %s kötüleşti: %s → %s', $label, $prevRating, $curRating)
                : sprintf('CrUX %s iyileşti: %s → %s', $label, $prevRating, $curRating);
        }

        return $changes;
    }
}
