<?php

declare(strict_types=1);

namespace App\Services\Llms;

final class LlmsTxtResult
{
    public function __construct(
        public readonly string $url,
        public readonly bool $found,
        public readonly ?string $preview = null,
    ) {
    }
}
