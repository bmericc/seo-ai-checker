<?php

declare(strict_types=1);

namespace App\Services\CanonicalHost;

final class CanonicalHostResult
{
    public function __construct(
        public readonly string $originalHost,
        public readonly string $canonicalHost,
        public readonly bool $redirected,
        public readonly ?int $redirectStatus = null,
    ) {
    }
}
