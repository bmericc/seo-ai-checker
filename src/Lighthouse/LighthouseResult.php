<?php

declare(strict_types=1);

namespace SeoAiChecker\Lighthouse;

final class LighthouseResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $strategy,
        public readonly ?int $performanceScore = null,
        public readonly ?int $seoScore = null,
        public readonly ?int $accessibilityScore = null,
        public readonly ?int $bestPracticesScore = null,
        public readonly ?string $error = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }
}
