<?php

declare(strict_types=1);

namespace App\Services\Crux;

final class CrUxResult
{
    /**
     * @param  array<string, array{label: string, p75: float|int, rating: string}>  $metrics
     */
    public function __construct(
        public readonly bool $configured,
        public readonly bool $found,
        public readonly string $origin,
        public readonly array $metrics = [],
        public readonly ?string $collectionPeriod = null,
        public readonly ?string $error = null,
    ) {
    }
}
