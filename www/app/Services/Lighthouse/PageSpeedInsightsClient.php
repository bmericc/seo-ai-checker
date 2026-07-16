<?php

declare(strict_types=1);

namespace App\Services\Lighthouse;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Google PageSpeed Insights (PSI) API'si uzerinden Lighthouse denetimi calistirir.
 * PSI, Lighthouse'u Google'in kendi sunucularinda calistirir; bu sayede sunucuda
 * Node.js/Chrome kurulumuna gerek kalmaz. Denetim tipik olarak 15-40 saniye surer.
 */
final class PageSpeedInsightsClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    private readonly Client $client;

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $strategy = 'mobile',
    ) {
        $this->client = new Client([
            'timeout' => 90.0,
            'connect_timeout' => 10.0,
            'http_errors' => false,
        ]);
    }

    public function analyze(string $url): LighthouseResult
    {
        $query = [
            'url' => $url,
            'strategy' => $this->strategy,
            'category' => ['performance', 'seo', 'accessibility', 'best-practices'],
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $query['key'] = $this->apiKey;
        }

        try {
            $response = $this->client->get(self::ENDPOINT, ['query' => $query]);
        } catch (GuzzleException $e) {
            return new LighthouseResult(url: $url, strategy: $this->strategy, error: $e->getMessage());
        }

        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), true);

        if ($status !== 200 || !is_array($data) || isset($data['error'])) {
            $message = is_array($data) && isset($data['error']['message'])
                ? (string) $data['error']['message']
                : sprintf('PageSpeed Insights istegi basarisiz (HTTP %d).', $status);

            return new LighthouseResult(url: $url, strategy: $this->strategy, error: $message);
        }

        $categories = $data['lighthouseResult']['categories'] ?? [];

        return new LighthouseResult(
            url: $url,
            strategy: $this->strategy,
            performanceScore: $this->scoreOf($categories, 'performance'),
            seoScore: $this->scoreOf($categories, 'seo'),
            accessibilityScore: $this->scoreOf($categories, 'accessibility'),
            bestPracticesScore: $this->scoreOf($categories, 'best-practices'),
            raw: $data,
        );
    }

    /**
     * @param array<string, mixed> $categories
     */
    private function scoreOf(array $categories, string $key): ?int
    {
        $score = $categories[$key]['score'] ?? null;

        return is_numeric($score) ? (int) round(((float) $score) * 100) : null;
    }
}
