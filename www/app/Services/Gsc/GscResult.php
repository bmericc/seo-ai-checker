<?php

declare(strict_types=1);

namespace App\Services\Gsc;

final class GscResult
{
    public function __construct(
        public readonly bool $configured,
        public readonly bool $verified,
        public readonly ?string $siteUrl = null,
        public readonly ?float $clicks = null,
        public readonly ?float $impressions = null,
        public readonly ?float $ctr = null,
        public readonly ?float $averagePosition = null,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
        public readonly ?string $error = null,
    ) {
    }
}
