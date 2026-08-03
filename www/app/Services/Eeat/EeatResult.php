<?php

declare(strict_types=1);

namespace App\Services\Eeat;

final class EeatResult
{
    /**
     * @param  array<string, array{present: bool, points: int, max: int}>  $signals
     */
    public function __construct(
        public readonly int $score,
        public readonly int $expertiseScore,
        public readonly int $authorityScore,
        public readonly int $trustScore,
        public readonly int $experienceScore,
        public readonly array $signals,
    ) {
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'expertise_score' => $this->expertiseScore,
            'authority_score' => $this->authorityScore,
            'trust_score' => $this->trustScore,
            'experience_score' => $this->experienceScore,
            'signals' => $this->signals,
        ];
    }
}
