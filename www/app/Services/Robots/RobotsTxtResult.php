<?php

declare(strict_types=1);

namespace App\Services\Robots;

final class RobotsTxtResult
{
    /**
     * @param  array<string, array{label: string, allowed: bool}>  $crawlers
     */
    public function __construct(
        public readonly string $url,
        public readonly bool $found,
        public readonly array $crawlers = [],
    ) {
    }
}
