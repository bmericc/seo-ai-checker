<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Check;
use Illuminate\Support\Collection;

/**
 * AI Overview "istikrar" metrigi - yeni sorgu yapmaz, her kontrolde zaten
 * kaydedilen ai_overview_present / ai_overview_target_cited gecmisinden
 * hesaplanir. Bir anahtar kelimenin son WINDOW engellenmemis kontrolune
 * bakar:
 *  - aio_rate: kontrollerin yuzde kacinda AI Overview cikti
 *  - citation_rate: AI Overview ciktigi kontrollerin yuzde kacinda site
 *    kaynak gosterildi (kalicilik)
 *  - change_rate: art arda iki kontrol arasinda (AIO var/yok, kaynak
 *    evet/hayir) durumunun yuzde kac geciste degistigi - dalgalanma
 * Engellenmis (blocked) kontroller AIO hakkinda bilgi tasimadigi icin
 * hesaba katilmaz; yoksa CAPTCHA'li gunler "AIO kayboldu" gibi gorunur.
 */
final class AiOverviewVolatility
{
    public const WINDOW = 10;

    public const LEVEL_STABLE = 'stable';
    public const LEVEL_MEDIUM = 'medium';
    public const LEVEL_HIGH = 'high';

    /** change_rate esikleri (yuzde, dahil degil). */
    private const MEDIUM_FROM = 20;
    private const HIGH_FROM = 50;

    /**
     * @param  iterable<Check>  $checks  Herhangi bir sirada; en yeni WINDOW engellenmemis kontrol kullanilir.
     * @return array{checks: int, aio_rate: ?int, citation_rate: ?int, change_rate: ?int, level: ?string}
     */
    public static function forChecks(iterable $checks): array
    {
        $usable = collect($checks)
            ->reject(fn (Check $check) => (bool) $check->blocked)
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values()
            ->take(-self::WINDOW)
            ->values();

        $total = $usable->count();
        if ($total === 0) {
            return ['checks' => 0, 'aio_rate' => null, 'citation_rate' => null, 'change_rate' => null, 'level' => null];
        }

        $present = $usable->filter(fn (Check $check) => (bool) $check->ai_overview_present);
        $cited = $present->filter(fn (Check $check) => (bool) $check->ai_overview_target_cited);

        $changeRate = null;
        if ($total >= 2) {
            $changes = 0;
            for ($i = 1; $i < $total; $i++) {
                if (self::state($usable[$i]) !== self::state($usable[$i - 1])) {
                    $changes++;
                }
            }
            $changeRate = (int) round($changes / ($total - 1) * 100);
        }

        return [
            'checks' => $total,
            'aio_rate' => (int) round($present->count() / $total * 100),
            'citation_rate' => $present->isEmpty() ? null : (int) round($cited->count() / $present->count() * 100),
            'change_rate' => $changeRate,
            'level' => self::level($changeRate),
        ];
    }

    /**
     * Domain'in tum anahtar kelimeleri icin metrik + ozet.
     *
     * @param  Collection<int, \App\Models\Keyword>  $keywords
     * @return array{keywords: list<array{keyword_id: int, keyword: string, checks: int, aio_rate: ?int, citation_rate: ?int, change_rate: ?int, level: ?string}>, summary: array{measured_keywords: int, aio_rate: ?int, citation_rate: ?int, volatile_keywords: int}}
     */
    public static function forKeywords(Collection $keywords): array
    {
        $checksByKeyword = Check::query()
            ->whereIn('keyword_id', $keywords->pluck('id'))
            ->select(['id', 'keyword_id', 'created_at', 'blocked', 'ai_overview_present', 'ai_overview_target_cited'])
            ->get()
            ->groupBy('keyword_id');

        $rows = [];
        foreach ($keywords as $keyword) {
            $rows[] = ['keyword_id' => $keyword->id, 'keyword' => $keyword->keyword]
                + self::forChecks($checksByKeyword->get($keyword->id, collect()));
        }

        $measured = collect($rows)->where('checks', '>', 0);
        $withAio = $measured->whereNotNull('citation_rate');

        return [
            'keywords' => $rows,
            'summary' => [
                'measured_keywords' => $measured->count(),
                'aio_rate' => $measured->isEmpty() ? null : (int) round($measured->avg('aio_rate')),
                'citation_rate' => $withAio->isEmpty() ? null : (int) round($withAio->avg('citation_rate')),
                'volatile_keywords' => $measured->where('level', self::LEVEL_HIGH)->count(),
            ],
        ];
    }

    public static function level(?int $changeRate): ?string
    {
        return match (true) {
            $changeRate === null => null,
            $changeRate >= self::HIGH_FROM => self::LEVEL_HIGH,
            $changeRate >= self::MEDIUM_FROM => self::LEVEL_MEDIUM,
            default => self::LEVEL_STABLE,
        };
    }

    private static function state(Check $check): string
    {
        $present = (bool) $check->ai_overview_present;

        return $present ? ($check->ai_overview_target_cited ? 'cited' : 'present') : 'absent';
    }
}
