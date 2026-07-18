<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Google Search Console Search Analytics API'sinden bir domain'in son 28
 * gunluk tiklama/gosterim/CTR/ortalama pozisyon verisini ceker. Kullaniciyi
 * temsilen (per-user OAuth access token ile) calisir - domain, o kullanicinin
 * GSC hesabinda dogrulanmis bir property degilse veri donmez.
 */
final class GscChecker
{
    private const SITES_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites';

    private const ANALYTICS_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';

    private const PERIOD_DAYS = 28;

    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function check(string $rootUrl, ?string $accessToken): GscResult
    {
        if ($accessToken === null || $accessToken === '') {
            return new GscResult(configured: false, verified: false);
        }

        $host = parse_url($rootUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return new GscResult(configured: true, verified: false, error: 'Gecersiz kok URL.');
        }

        try {
            $sitesResponse = $this->client->get(self::SITES_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new GscResult(configured: true, verified: false, error: $e->getMessage());
        }

        if ($sitesResponse->getStatusCode() !== 200) {
            return new GscResult(configured: true, verified: false, error: $this->describeError($sitesResponse->getStatusCode(), (string) $sitesResponse->getBody()));
        }

        $sitesData = json_decode((string) $sitesResponse->getBody(), true);
        $siteUrl = $this->matchSite($sitesData['siteEntry'] ?? [], $host);

        if ($siteUrl === null) {
            return new GscResult(configured: true, verified: false);
        }

        $endDate = now();
        $startDate = now()->subDays(self::PERIOD_DAYS);

        try {
            $analyticsResponse = $this->client->post(
                sprintf(self::ANALYTICS_ENDPOINT, rawurlencode($siteUrl)),
                [
                    'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                    'json' => [
                        'startDate' => $startDate->toDateString(),
                        'endDate' => $endDate->toDateString(),
                        'rowLimit' => 1,
                    ],
                    'http_errors' => false,
                ]
            );
        } catch (GuzzleException $e) {
            return new GscResult(configured: true, verified: true, siteUrl: $siteUrl, error: $e->getMessage());
        }

        if ($analyticsResponse->getStatusCode() !== 200) {
            return new GscResult(
                configured: true,
                verified: true,
                siteUrl: $siteUrl,
                error: $this->describeError($analyticsResponse->getStatusCode(), (string) $analyticsResponse->getBody()),
            );
        }

        $analyticsData = json_decode((string) $analyticsResponse->getBody(), true);
        $row = $analyticsData['rows'][0] ?? null;

        return new GscResult(
            configured: true,
            verified: true,
            siteUrl: $siteUrl,
            clicks: $row['clicks'] ?? 0.0,
            impressions: $row['impressions'] ?? 0.0,
            ctr: $row['ctr'] ?? 0.0,
            averagePosition: $row['position'] ?? null,
            periodStart: $startDate->toDateString(),
            periodEnd: $endDate->toDateString(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $siteEntries
     */
    private function matchSite(array $siteEntries, string $host): ?string
    {
        $candidates = [
            'sc-domain:' . $host,
            'https://' . $host . '/',
            'http://' . $host . '/',
        ];

        foreach ($siteEntries as $entry) {
            $siteUrl = $entry['siteUrl'] ?? null;
            if (is_string($siteUrl) && in_array($siteUrl, $candidates, true)) {
                return $siteUrl;
            }
        }

        return null;
    }

    private function describeError(int $status, string $body): string
    {
        $data = json_decode($body, true);
        $message = $data['error']['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return "HTTP {$status}: {$message}";
        }

        return "HTTP {$status}";
    }
}
