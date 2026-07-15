<?php

declare(strict_types=1);

namespace App\Services\Serp;

use App\Support\Domain;

final class AiOverviewResult
{
    /**
     * @param string[] $citedDomains
     */
    public function __construct(
        public readonly bool $present,
        public readonly array $citedDomains = [],
        public readonly ?string $note = null,
    ) {
    }

    public function citesDomain(string $domain): bool
    {
        foreach ($this->citedDomains as $cited) {
            if (Domain::equals($cited, $domain)) {
                return true;
            }
        }

        return false;
    }
}
