<?php

declare(strict_types=1);

namespace App\Services\Ga4;

final class Ga4Property
{
    public function __construct(
        public readonly string $propertyId,
        public readonly string $label,
    ) {
    }
}
