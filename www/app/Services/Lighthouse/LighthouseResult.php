<?php

declare(strict_types=1);

namespace App\Services\Lighthouse;

final class LighthouseResult
{
    private const METRIC_AUDITS = [
        'first-contentful-paint' => 'First Contentful Paint',
        'largest-contentful-paint' => 'Largest Contentful Paint',
        'total-blocking-time' => 'Total Blocking Time',
        'cumulative-layout-shift' => 'Cumulative Layout Shift',
        'speed-index' => 'Speed Index',
        'interactive' => 'Time to Interactive',
    ];

    /**
     * @param  array<string, mixed>|null  $raw  PageSpeed Insights API'sinin tam JSON yaniti
     *         (audits, kategori detaylari, CrUX field data, vb. - arsivleme ve
     *         gecmisle kiyaslama icin oldugu gibi saklanir).
     */
    public function __construct(
        public readonly string $url,
        public readonly string $strategy,
        public readonly ?int $performanceScore = null,
        public readonly ?int $seoScore = null,
        public readonly ?int $accessibilityScore = null,
        public readonly ?int $bestPracticesScore = null,
        public readonly ?string $error = null,
        public readonly ?array $raw = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Core Web Vitals ve diger onemli performans metriklerini ham veriden
     * cikarir; skorlarin arkasindaki gercek olcumleri (ör. "LCP: 2.4 s")
     * gostermek icin kullanilir.
     *
     * @return array<string, array{label: string, displayValue: ?string, score: ?float}>
     */
    public function metrics(): array
    {
        return self::metricsFromRaw($this->raw);
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, array{label: string, displayValue: ?string, score: ?float}>
     */
    public static function metricsFromRaw(?array $raw): array
    {
        $audits = $raw['lighthouseResult']['audits'] ?? [];
        if (!is_array($audits)) {
            return [];
        }

        $metrics = [];
        foreach (self::METRIC_AUDITS as $key => $label) {
            if (!isset($audits[$key])) {
                continue;
            }

            $audit = $audits[$key];
            $metrics[$key] = [
                'label' => $label,
                'displayValue' => is_string($audit['displayValue'] ?? null) ? $audit['displayValue'] : null,
                'score' => is_numeric($audit['score'] ?? null) ? (float) $audit['score'] : null,
            ];
        }

        return $metrics;
    }
}
