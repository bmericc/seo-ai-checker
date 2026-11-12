<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

use App\Models\Keyword;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Keyword kayitlarinin hacim/CPC/KD/niyet metriklerini tazeler. Arama
 * hacmi aylik guncellenen bir veri oldugu icin STALE_AFTER_DAYS'den yeni
 * metrigi olan kelimeler tekrar sorgulanmaz (maliyet olusmaz).
 */
final class KeywordMetricsUpdater
{
    public const STALE_AFTER_DAYS = 30;

    public function __construct(private readonly KeywordMetricsFetcher $fetcher)
    {
    }

    public function isConfigured(): bool
    {
        return $this->fetcher->isConfigured();
    }

    /**
     * @param  Collection<int, Keyword>  $keywords
     * @return int metrigi guncellenen kelime sayisi
     */
    public function refresh(Collection $keywords, bool $force = false): int
    {
        if (!$this->fetcher->isConfigured()) {
            return 0;
        }

        $targets = $force
            ? $keywords
            : $keywords->filter(fn (Keyword $k) => $this->isStale($k));

        if ($targets->isEmpty()) {
            return 0;
        }

        try {
            $metrics = $this->fetcher->fetch($targets->pluck('keyword')->all());
        } catch (DataForSeoException $e) {
            // Job basarili biter (tekrar denenmez); hata sadece kelimeye
            // yazilirsa gozden kacar (orn. bakiye bittiginde HTTP 402).
            Log::warning('DataForSEO keyword metrics request failed', [
                'error' => $e->getMessage(),
                'keyword_count' => $targets->count(),
            ]);

            foreach ($targets as $keyword) {
                $keyword->update(['metrics_error' => $e->getMessage()]);
            }

            return 0;
        }

        foreach ($targets as $keyword) {
            $found = $metrics[mb_strtolower(trim($keyword->keyword))] ?? null;

            // Bulunamayan kelime de "kontrol edildi" sayilir: DataForSEO'da
            // verisi yok demektir, her seferinde tekrar sormanin anlami yok.
            $keyword->update(array_merge(
                $found?->toKeywordAttributes() ?? (new KeywordMetrics($keyword->keyword))->toKeywordAttributes(),
                ['metrics_fetched_at' => now(), 'metrics_error' => null],
            ));
        }

        return $targets->count();
    }

    private function isStale(Keyword $keyword): bool
    {
        return $keyword->metrics_fetched_at === null
            || $keyword->metrics_fetched_at->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
