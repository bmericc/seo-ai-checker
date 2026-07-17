<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

final class SitemapResult
{
    public function __construct(
        public readonly string $url,
        public readonly bool $found,
        public readonly bool $isValidXml = false,
        public readonly bool $isSitemapIndex = false,
        public readonly ?int $urlCount = null,
        public readonly ?string $error = null,
    ) {
    }
}
