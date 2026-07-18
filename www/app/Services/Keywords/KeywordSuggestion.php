<?php

declare(strict_types=1);

namespace App\Services\Keywords;

final class KeywordSuggestion
{
    public function __construct(
        public readonly string $phrase,
        public readonly float $score,
    ) {
    }
}
