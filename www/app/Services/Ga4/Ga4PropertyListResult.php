<?php

declare(strict_types=1);

namespace App\Services\Ga4;

final class Ga4PropertyListResult
{
    /**
     * @param  Ga4Property[]  $properties
     */
    public function __construct(
        public readonly bool $configured,
        public readonly array $properties = [],
        public readonly ?string $error = null,
    ) {
    }
}
