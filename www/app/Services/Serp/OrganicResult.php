<?php

declare(strict_types=1);

namespace App\Services\Serp;

final class OrganicResult
{
    public function __construct(
        public readonly int $position,
        public readonly string $url,
        public readonly string $domain,
        public readonly string $title,
    ) {
    }
}
