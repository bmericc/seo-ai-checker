<?php

declare(strict_types=1);

namespace App\Services\Keywords;

final class KeywordSuggestion
{
    /**
     * @param  int  $pages  Ifadenin gectigi sayfa sayisi (site geneli birlestirmede; tek sayfa onerisinde 1).
     * @param  ?string  $url  Ifadenin en guclu gectigi sayfa (site geneli birlestirmede).
     */
    public function __construct(
        public readonly string $phrase,
        public readonly float $score,
        public readonly int $pages = 1,
        public readonly ?string $url = null,
    ) {
    }

    /**
     * @return array{phrase: string, score: float, pages: int, url: ?string}
     */
    public function toArray(): array
    {
        return [
            'phrase' => $this->phrase,
            'score' => round($this->score, 4),
            'pages' => $this->pages,
            'url' => $this->url,
        ];
    }
}
