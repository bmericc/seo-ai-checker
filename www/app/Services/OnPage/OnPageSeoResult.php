<?php

declare(strict_types=1);

namespace App\Services\OnPage;

final class OnPageSeoResult
{
    /**
     * @param  string[]  $h1s
     * @param  array<string, ?string>  $ogTags  og:title/og:description/og:image/og:type/og:url => deger (yoksa null)
     * @param  string[]  $schemaTypes  Sayfada bulunan tum JSON-LD @type degerleri (tekil, sirali)
     * @param  string[]  $deprecatedSchemaTypes  $schemaTypes icinde bilinen "kullanilmamasi gereken" tiplerin alt kumesi
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $title,
        public readonly ?string $metaDescription,
        public readonly ?string $canonical,
        public readonly ?string $metaRobots,
        public readonly array $h1s,
        public readonly int $h2Count,
        public readonly int $wordCount,
        public readonly ?string $keyword,
        public readonly float $keywordDensityPercent,
        public readonly bool $keywordInTitle,
        public readonly bool $keywordInH1,
        public readonly bool $keywordInDescription,
        public readonly int $imagesMissingAlt,
        public readonly int $internalLinks,
        public readonly int $externalLinks,
        public readonly bool $hasStructuredData,
        public readonly float $fetchTimeMs,
        public readonly string $canonicalStatus = 'missing',
        public readonly bool $headingHierarchySkip = false,
        public readonly array $ogTags = [],
        public readonly ?string $twitterCard = null,
        public readonly array $schemaTypes = [],
        public readonly array $deprecatedSchemaTypes = [],
    ) {
    }

    public function titleLength(): int
    {
        return $this->title !== null ? mb_strlen($this->title) : 0;
    }

    public function descriptionLength(): int
    {
        return $this->metaDescription !== null ? mb_strlen($this->metaDescription) : 0;
    }

    public function h1Count(): int
    {
        return count($this->h1s);
    }
}
