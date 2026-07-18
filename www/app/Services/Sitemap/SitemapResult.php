<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

final class SitemapResult
{
    /**
     * @param  string[]  $urls  Sitemap'ten (sitemap index ise alt-sitemap'lerden, sinirli sayida) toplanan sayfa URL'leri.
     */
    public function __construct(
        public readonly string $url,
        public readonly bool $found,
        public readonly bool $isValidXml = false,
        public readonly bool $isSitemapIndex = false,
        public readonly ?int $urlCount = null,
        public readonly ?string $error = null,
        public readonly array $urls = [],
        public readonly bool $urlsTruncated = false,
    ) {
    }
}
