<?php

declare(strict_types=1);

namespace App\Services\Crux;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Chrome UX Report (CrUX) Query Record API'sinden bir origin icin gercek
 * kullanici (field) Core Web Vitals verisini ceker. Lighthouse/PSI'nin
 * aksine bu lab (sentetik) degil, Chrome kullanicilarindan toplanan
 * gercek veridir; ancak yeterli trafigi olmayan origin'ler icin veri
 * bulunmayabilir (404).
 */
final class CrUxChecker
{
    private const ENDPOINT = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';

    private const METRICS = [
        'largest_contentful_paint' => ['label' => 'LCP', 'good' => 2500, 'needs_improvement' => 4000],
        'interaction_to_next_paint' => ['label' => 'INP', 'good' => 200, 'needs_improvement' => 500],
        'cumulative_layout_shift' => ['label' => 'CLS', 'good' => 0.1, 'needs_improvement' => 0.25],
        'first_contentful_paint' => ['label' => 'FCP', 'good' => 1800, 'needs_improvement' => 3000],
        'experimental_time_to_first_byte' => ['label' => 'TTFB', 'good' => 800, 'needs_improvement' => 1800],
    ];

    public function __construct(
        private readonly Client $client,
        private readonly ?string $apiKey,
    ) {
    }

    public function check(string $origin): CrUxResult
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return new CrUxResult(configured: false, found: false, origin: $origin);
        }

        try {
            $response = $this->client->post(self::ENDPOINT, [
                'query' => ['key' => $this->apiKey],
                'json' => ['origin' => $origin],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new CrUxResult(configured: true, found: false, origin: $origin, error: $e->getMessage());
        }

        $status = $response->getStatusCode();

        if ($status === 404) {
            return new CrUxResult(configured: true, found: false, origin: $origin);
        }

        if ($status !== 200) {
            return new CrUxResult(configured: true, found: false, origin: $origin, error: "HTTP {$status}");
        }

        $data = json_decode((string) $response->getBody(), true);
        $rawMetrics = $data['record']['metrics'] ?? [];

        $metrics = [];
        foreach (self::METRICS as $key => $meta) {
            $p75 = $rawMetrics[$key]['percentiles']['p75'] ?? null;
            if ($p75 === null) {
                continue;
            }

            $metrics[$key] = [
                'label' => $meta['label'],
                'p75' => $p75,
                'rating' => $this->rate((float) $p75, $meta['good'], $meta['needs_improvement']),
            ];
        }

        return new CrUxResult(
            configured: true,
            found: $metrics !== [],
            origin: $origin,
            metrics: $metrics,
            collectionPeriod: $this->formatPeriod($data['record']['collectionPeriod'] ?? null),
        );
    }

    private function rate(float $p75, float $good, float $needsImprovement): string
    {
        if ($p75 <= $good) {
            return 'good';
        }

        if ($p75 <= $needsImprovement) {
            return 'needs_improvement';
        }

        return 'poor';
    }

    /**
     * @param  array<string, mixed>|null  $period
     */
    private function formatPeriod(?array $period): ?string
    {
        $first = $period['firstDate'] ?? null;
        $last = $period['lastDate'] ?? null;

        if (!is_array($first) || !is_array($last)) {
            return null;
        }

        $format = static fn (array $d): string => sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);

        return $format($first) . ' — ' . $format($last);
    }
}
