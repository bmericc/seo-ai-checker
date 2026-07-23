<?php

declare(strict_types=1);

namespace App\Services\Bing;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Bing Webmaster Tools API'sinden inbound link (backlink) verisi ceker.
 * Kullaniciya ozel OAuth 2.0 access token ile calisir (bkz. BingController,
 * BingTokenService) - bu yuzden yalnizca kendi Bing hesabini baglayan
 * kullanicinin ONCEDEN DOGRULANMIS siteleri icin veri doner. Rakip ya da
 * genel bir backlink indeksi DEGILDIR.
 */
final class BingBacklinksChecker
{
    private const BASE_URL = 'https://ssl.bing.com/webmaster/api.svc/json/%s';

    private const TOP_PAGES_LIMIT = 5;

    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function check(string $rootUrl, ?string $accessToken): BingBacklinksResult
    {
        if ($accessToken === null || $accessToken === '') {
            return new BingBacklinksResult(configured: false, verified: false);
        }

        $host = parse_url($rootUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return new BingBacklinksResult(configured: true, verified: false, error: 'Gecersiz kok URL.');
        }

        try {
            $sitesResponse = $this->client->get(sprintf(self::BASE_URL, 'GetUserSites'), [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new BingBacklinksResult(configured: true, verified: false, error: $e->getMessage());
        }

        if ($sitesResponse->getStatusCode() !== 200) {
            return new BingBacklinksResult(configured: true, verified: false, error: $this->describeError($sitesResponse->getStatusCode(), (string) $sitesResponse->getBody()));
        }

        $siteUrl = $this->matchSite($this->unwrap(json_decode((string) $sitesResponse->getBody(), true)), $host);

        if ($siteUrl === null) {
            return new BingBacklinksResult(configured: true, verified: false);
        }

        try {
            $linksResponse = $this->client->get(sprintf(self::BASE_URL, 'GetLinkCounts'), [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'query' => ['siteUrl' => $siteUrl, 'page' => 0],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new BingBacklinksResult(configured: true, verified: true, siteUrl: $siteUrl, error: $e->getMessage());
        }

        if ($linksResponse->getStatusCode() !== 200) {
            return new BingBacklinksResult(
                configured: true,
                verified: true,
                siteUrl: $siteUrl,
                error: $this->describeError($linksResponse->getStatusCode(), (string) $linksResponse->getBody()),
            );
        }

        $entries = $this->unwrap(json_decode((string) $linksResponse->getBody(), true));

        $totalLinks = 0;
        $pages = [];
        foreach ($entries as $entry) {
            $url = $entry['Url'] ?? $entry['url'] ?? null;
            $count = (int) ($entry['Count'] ?? $entry['count'] ?? 0);

            if (!is_string($url)) {
                continue;
            }

            $totalLinks += $count;
            $pages[] = ['url' => $url, 'count' => $count];
        }

        usort($pages, static fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return new BingBacklinksResult(
            configured: true,
            verified: true,
            siteUrl: $siteUrl,
            totalLinks: $totalLinks,
            pagesWithLinks: count($pages),
            topPages: array_slice($pages, 0, self::TOP_PAGES_LIMIT),
        );
    }

    /**
     * Bing Webmaster JSON API bazi uc noktalarda sonucu dogrudan, bazilarinda
     * eski ASMX aliskanligiyla {"d": [...]} sarmalayicisi icinde doner.
     */
    private function unwrap(mixed $data): array
    {
        if (is_array($data) && array_key_exists('d', $data)) {
            $data = $data['d'];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, mixed>  $siteEntries
     */
    private function matchSite(array $siteEntries, string $host): ?string
    {
        // Bing Webmaster Tools kullanicilar bir siteyi "www." ile ya da
        // onsuz dogrulayabiliyor, birbirinden bagimsiz iki ayri kayit
        // olarak (bkz. GetUserSites cikti - "https://tarti.com/" ve
        // "https://www.avustralyarehberi.tr/" ayni hesapta karisik
        // halde donuyor). Domain'imiz "www"siz saklansa da dogrulama
        // "www." ile yapilmis olabilir - bu yuzden her iki varyanti da
        // adayllar arasina ekliyoruz, hangi tarafta oldugundan bagimsiz.
        $bareHost = preg_replace('/^www\./i', '', $host);
        $hosts = array_unique([$host, $bareHost, 'www.' . $bareHost]);

        $candidates = [];
        foreach ($hosts as $h) {
            $candidates[] = 'https://' . $h . '/';
            $candidates[] = 'http://' . $h . '/';
            $candidates[] = 'https://' . $h;
            $candidates[] = 'http://' . $h;
        }

        foreach ($siteEntries as $entry) {
            $siteUrl = is_string($entry) ? $entry : ($entry['Url'] ?? $entry['url'] ?? null);
            if (is_string($siteUrl) && in_array(rtrim($siteUrl, '/') . '/', array_map(static fn ($c) => rtrim($c, '/') . '/', $candidates), true)) {
                return $siteUrl;
            }
        }

        return null;
    }

    private function describeError(int $status, string $body): string
    {
        $data = json_decode($body, true);
        $message = $data['Message'] ?? $data['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return "HTTP {$status}: {$message}";
        }

        return "HTTP {$status}";
    }
}
