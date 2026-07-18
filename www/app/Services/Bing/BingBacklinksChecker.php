<?php

declare(strict_types=1);

namespace App\Services\Bing;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Bing Webmaster Tools API'sinden inbound link (backlink) verisi ceker.
 * OAuth degil, hesap-genelinde tek bir API key ile calisir (CrUX/PSI ile
 * ayni model) - bu yuzden yalnizca bu API key'in bagli oldugu Bing
 * Webmaster hesabinda ONCEDEN DOGRULANMIS siteler icin veri doner. Rakip
 * ya da genel bir backlink indeksi DEGILDIR; sadece kullanicinin kendi
 * dogruladigi siteler icin calisir.
 */
final class BingBacklinksChecker
{
    private const BASE_URL = 'https://ssl.bing.com/webmaster/api.svc/json/%s';

    private const TOP_PAGES_LIMIT = 5;

    public function __construct(
        private readonly Client $client,
        private readonly ?string $apiKey,
    ) {
    }

    public function check(string $rootUrl): BingBacklinksResult
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return new BingBacklinksResult(configured: false, verified: false);
        }

        $host = parse_url($rootUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return new BingBacklinksResult(configured: true, verified: false, error: 'Gecersiz kok URL.');
        }

        try {
            $sitesResponse = $this->client->get(sprintf(self::BASE_URL, 'GetUserSites'), [
                'query' => ['apikey' => $this->apiKey],
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
                'query' => ['apikey' => $this->apiKey, 'siteUrl' => $siteUrl, 'page' => 0],
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
        $candidates = [
            'https://' . $host . '/',
            'http://' . $host . '/',
            'https://' . $host,
            'http://' . $host,
        ];

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
