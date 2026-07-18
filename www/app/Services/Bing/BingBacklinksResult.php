<?php

declare(strict_types=1);

namespace App\Services\Bing;

final class BingBacklinksResult
{
    /**
     * @param  array<int, array{url: string, count: int}>  $topPages
     */
    public function __construct(
        public readonly bool $configured,
        public readonly bool $verified,
        public readonly ?string $siteUrl = null,
        public readonly int $totalLinks = 0,
        public readonly int $pagesWithLinks = 0,
        public readonly array $topPages = [],
        public readonly ?string $error = null,
    ) {
    }
}
