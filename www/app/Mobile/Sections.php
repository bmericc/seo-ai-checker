<?php

declare(strict_types=1);

namespace App\Mobile;

/**
 * Mobil ekran bolumleri icin kucuk yardimcilar. Bolum tipleri ve
 * alanlari uygulamadaki src/screens/DomainScreen.tsx ile birlikte
 * degisir; yeni tip eklemek eski uygulamayi bozmaz (bilinmeyen tip
 * "Web'de aç" olarak gosterilir).
 */
final class Sections
{
    /**
     * @return array<string, mixed>
     */
    public static function notice(string $title, string $text, ?string $webUrl = null, string $level = 'info'): array
    {
        return array_filter([
            'type' => 'notice',
            'title' => $title,
            'data' => ['level' => $level, 'text' => $text],
            'web_url' => $webUrl,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array{label: string, tone: string}
     */
    public static function badge(string $label, string $tone): array
    {
        return ['label' => $label, 'tone' => $tone];
    }

    /**
     * Gunluk seriler (ScoreHistoryBuilder). Hic degeri olmayan seri ve
     * iki noktadan az veri varsa bolum gosterilmez.
     *
     * @param  list<string>  $labels
     * @param  list<array{0: string, 1: list<?float>}>  $series
     * @return ?array<string, mixed>
     */
    public static function chart(string $title, array $labels, array $series, int $maxPoints, bool $invert = false, string $unit = ''): ?array
    {
        $offset = max(0, count($labels) - $maxPoints);
        $labels = array_slice($labels, $offset);

        $series = array_values(array_filter(array_map(fn (array $s) => [
            'name' => $s[0],
            'values' => array_map(fn ($v) => $v === null ? null : round((float) $v, 2), array_slice($s[1], $offset)),
        ], $series), fn (array $s) => count(array_filter($s['values'], fn ($v) => $v !== null)) >= 2));

        if ($series === []) {
            return null;
        }

        return [
            'type' => 'chart',
            'title' => $title,
            'data' => [
                'labels' => $labels,
                'series' => $series,
                // Sira gibi "kucuk daha iyi" degerlerde eksen ters cizilir.
                'invert' => $invert,
                'unit' => $unit,
            ],
        ];
    }

    public static function number(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0, ',', '.') : '—';
    }

    public static function decimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, app()->getLocale() === 'tr' ? ',' : '.', app()->getLocale() === 'tr' ? '.' : ',');
    }

    public static function delta(mixed $current, mixed $previous): ?string
    {
        if (!is_numeric($current) || !is_numeric($previous) || (float) $current === (float) $previous) {
            return null;
        }

        $diff = (float) $current - (float) $previous;

        return ($diff > 0 ? '+' : '−').self::number(abs($diff));
    }
}
