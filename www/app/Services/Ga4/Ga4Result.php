<?php

declare(strict_types=1);

namespace App\Services\Ga4;

final class Ga4Result
{
    public function __construct(
        public readonly bool $configured,
        public readonly ?string $propertyId = null,
        public readonly ?float $totalSessions = null,
        public readonly ?float $organicSessions = null,
        public readonly ?float $activeUsers = null,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
        public readonly ?string $error = null,
    ) {
    }
}
