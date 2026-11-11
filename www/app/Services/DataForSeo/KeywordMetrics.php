<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

/**
 * Bir anahtar kelimenin DataForSEO Labs "keyword_overview" metrikleri.
 * Tum alanlar null olabilir: DataForSEO veritabaninda olmayan (cok dusuk
 * hacimli ya da yeni) kelimeler icin metrik donmez.
 */
final class KeywordMetrics
{
    /**
     * @param  list<array{year: int, month: int, search_volume: int}>  $monthlySearches
     */
    public function __construct(
        public readonly string $keyword,
        public readonly ?int $searchVolume = null,
        public readonly ?float $cpc = null,
        /** 0-1 arasi ucretli reklam rekabet orani. */
        public readonly ?float $competition = null,
        /** 0-100 arasi organik siralama zorlugu. */
        public readonly ?int $keywordDifficulty = null,
        /** informational, navigational, commercial, transactional */
        public readonly ?string $searchIntent = null,
        public readonly array $monthlySearches = [],
    ) {
    }

    /**
     * Keyword modelindeki kolonlara yazilacak alanlar.
     *
     * @return array<string, mixed>
     */
    public function toKeywordAttributes(): array
    {
        return [
            'search_volume' => $this->searchVolume,
            'cpc' => $this->cpc,
            'competition' => $this->competition,
            'keyword_difficulty' => $this->keywordDifficulty,
            'search_intent' => $this->searchIntent,
            'monthly_searches' => $this->monthlySearches,
        ];
    }
}
