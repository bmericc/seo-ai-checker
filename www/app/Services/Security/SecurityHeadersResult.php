<?php

declare(strict_types=1);

namespace App\Services\Security;

final class SecurityHeadersResult
{
    /**
     * @param  array<string, ?string>  $headers  Header adi => deger (yoksa null)
     */
    public function __construct(
        public readonly bool $reachable,
        public readonly array $headers = [],
        public readonly bool $httpRedirectsToHttps = false,
    ) {
    }
}
