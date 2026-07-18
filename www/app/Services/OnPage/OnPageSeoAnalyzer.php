<?php

declare(strict_types=1);

namespace App\Services\OnPage;

use GuzzleHttp\Client;
use App\Support\Domain;
use Symfony\Component\DomCrawler\Crawler;

final class OnPageSeoAnalyzer
{
    private const OG_PROPERTIES = ['og:title', 'og:description', 'og:image', 'og:type', 'og:url'];

    /**
     * Schema.org'un kendisi "deprecated" olarak isaretlemez, ancak bu tipler
     * yaygin WordPress SEO eklentilerinin otomatik ekledigi, hicbir gercek
     * bilgi tasimayan ve modern rich-result kilavuzlarinda onerilmeyen
     * "sayfa iskeleti" tipleridir (header/footer/sidebar/reklam alani);
     * SEO araclari bunlari genelde gurultu/atik isaretleme olarak flagler.
     */
    private const DEPRECATED_SCHEMA_TYPES = [
        'WPHeader', 'WPFooter', 'WPSideBar', 'WPAdBlock', 'WebPageElement',
    ];

    public function __construct(private readonly Client $client)
    {
    }

    public function analyze(string $url, ?string $keyword = null): OnPageSeoResult
    {
        $start = microtime(true);
        $response = $this->client->get($url);
        $fetchTimeMs = (microtime(true) - $start) * 1000;

        $html = (string) $response->getBody();
        $crawler = new Crawler($html, $url);

        $title = $this->firstText($crawler, 'title');
        $metaDescription = $this->metaContent($crawler, 'description');
        $metaRobots = $this->metaContent($crawler, 'robots');
        $canonical = $this->linkHref($crawler, 'canonical');

        $h1s = $crawler->filter('h1')->each(static fn (Crawler $node) => trim($node->text('')));
        $h2Count = $crawler->filter('h2')->count();

        $bodyText = $crawler->filter('body')->count() > 0 ? $crawler->filter('body')->text('') : '';
        $normalizedText = trim(preg_replace('/\s+/u', ' ', $bodyText) ?? '');
        $words = $normalizedText === '' ? [] : preg_split('/\s+/u', $normalizedText);
        $wordCount = is_array($words) ? count($words) : 0;

        $keywordDensity = 0.0;
        $keywordInTitle = false;
        $keywordInH1 = false;
        $keywordInDescription = false;

        if ($keyword !== null && trim($keyword) !== '') {
            $lowerKeyword = mb_strtolower(trim($keyword));
            $lowerText = mb_strtolower($normalizedText);

            $occurrences = $lowerText === '' ? 0 : substr_count($lowerText, $lowerKeyword);
            $keywordWordCount = max(1, count(preg_split('/\s+/u', $lowerKeyword) ?: [$lowerKeyword]));
            $keywordDensity = $wordCount > 0
                ? ($occurrences * $keywordWordCount / $wordCount) * 100
                : 0.0;

            $keywordInTitle = $title !== null && str_contains(mb_strtolower($title), $lowerKeyword);
            $keywordInDescription = $metaDescription !== null && str_contains(mb_strtolower($metaDescription), $lowerKeyword);
            foreach ($h1s as $h1) {
                if (str_contains(mb_strtolower($h1), $lowerKeyword)) {
                    $keywordInH1 = true;
                    break;
                }
            }
        }

        $imageStats = $this->imageStats($crawler);

        $pageHost = Domain::fromUrl($url);
        $internalLinks = 0;
        $externalLinks = 0;
        foreach ($crawler->filter('a[href]') as $node) {
            $href = $node->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            $absolute = $this->toAbsoluteUrl($href, $url);
            $linkHost = $absolute !== null ? Domain::fromUrl($absolute) : null;

            if ($linkHost === null) {
                continue;
            }

            if ($pageHost !== null && Domain::equals($linkHost, $pageHost)) {
                $internalLinks++;
            } else {
                $externalLinks++;
            }
        }

        $schemaTypes = $this->schemaTypes($crawler);
        $deprecatedSchemaTypes = array_values(array_intersect($schemaTypes, self::DEPRECATED_SCHEMA_TYPES));

        $ogTags = [];
        foreach (self::OG_PROPERTIES as $property) {
            $ogTags[$property] = $this->metaProperty($crawler, $property);
        }

        $hreflangTags = $this->hreflangTags($crawler);
        $hreflangCodes = $this->hreflangLangCodes($crawler);
        $hreflangIssues = $this->hreflangIssues($hreflangTags, $hreflangCodes, $url);

        return new OnPageSeoResult(
            url: $url,
            title: $title,
            metaDescription: $metaDescription,
            canonical: $canonical,
            metaRobots: $metaRobots,
            h1s: $h1s,
            h2Count: $h2Count,
            wordCount: $wordCount,
            keyword: $keyword,
            keywordDensityPercent: round($keywordDensity, 2),
            keywordInTitle: $keywordInTitle,
            keywordInH1: $keywordInH1,
            keywordInDescription: $keywordInDescription,
            imagesMissingAlt: $imageStats['missing_alt'],
            internalLinks: $internalLinks,
            externalLinks: $externalLinks,
            hasStructuredData: $schemaTypes !== [],
            fetchTimeMs: round($fetchTimeMs, 1),
            canonicalStatus: $this->canonicalStatus($canonical, $url),
            headingHierarchySkip: $this->hasHeadingHierarchySkip($crawler),
            ogTags: $ogTags,
            twitterCard: $this->metaContent($crawler, 'twitter:card'),
            schemaTypes: $schemaTypes,
            deprecatedSchemaTypes: $deprecatedSchemaTypes,
            hreflangTags: $hreflangTags,
            hreflangIssues: $hreflangIssues,
            imageStats: $imageStats,
        );
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);

        return $node->count() > 0 ? trim($node->first()->text('')) : null;
    }

    private function metaContent(Crawler $crawler, string $name): ?string
    {
        $node = $crawler->filter(sprintf('meta[name="%s"]', $name));
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');

        return $content !== null && trim($content) !== '' ? trim($content) : null;
    }

    private function metaProperty(Crawler $crawler, string $property): ?string
    {
        $node = $crawler->filter(sprintf('meta[property="%s"]', $property));
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');

        return $content !== null && trim($content) !== '' ? trim($content) : null;
    }

    private function linkHref(Crawler $crawler, string $rel): ?string
    {
        $node = $crawler->filter(sprintf('link[rel="%s"]', $rel));

        return $node->count() > 0 ? $node->first()->attr('href') : null;
    }

    /**
     * @return 'missing'|'self'|'different'
     */
    private function canonicalStatus(?string $canonical, string $pageUrl): string
    {
        if ($canonical === null || trim($canonical) === '') {
            return 'missing';
        }

        $absoluteCanonical = $this->toAbsoluteUrl($canonical, $pageUrl) ?? $canonical;

        return $this->normalizeUrlForComparison($absoluteCanonical) === $this->normalizeUrlForComparison($pageUrl)
            ? 'self'
            : 'different';
    }

    private function normalizeUrlForComparison(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = rtrim($parts['path'] ?? '/', '/');

        return $host . ($path === '' ? '/' : $path);
    }

    /**
     * h1-h6 basliklarini dokuman sirasina gore dolasip bir seviyeden
     * digerine (ornegin h2'den dogrudan h4'e) atlama olup olmadigini tespit
     * eder. Geriye donus (h3 -> h2) normaldir, sadece ileri atlamalar
     * isaretlenir.
     */
    private function hasHeadingHierarchySkip(Crawler $crawler): bool
    {
        $levels = $crawler->filter('h1, h2, h3, h4, h5, h6')->each(
            static fn (Crawler $node) => (int) substr($node->nodeName(), 1)
        );

        $previous = null;
        foreach ($levels as $level) {
            if ($previous !== null && $level > $previous + 1) {
                return true;
            }
            $previous = $level;
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function schemaTypes(Crawler $crawler): array
    {
        $types = [];

        foreach ($crawler->filter('script[type="application/ld+json"]') as $node) {
            $data = json_decode($node->textContent ?? '', true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($this->extractTypesFromJsonLd($data) as $type) {
                $types[$type] = true;
            }
        }

        return array_keys($types);
    }

    /**
     * @param  array<mixed>  $data
     * @return string[]
     */
    private function extractTypesFromJsonLd(array $data): array
    {
        // JSON-LD ya tek bir nesne, ya bir nesne listesi, ya da "@graph"
        // altinda bir nesne listesi olabilir.
        $nodes = isset($data['@graph']) && is_array($data['@graph'])
            ? $data['@graph']
            : (array_is_list($data) ? $data : [$data]);

        $types = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || !isset($node['@type'])) {
                continue;
            }

            $type = $node['@type'];
            if (is_string($type)) {
                $types[] = $type;
            } elseif (is_array($type)) {
                foreach ($type as $t) {
                    if (is_string($t)) {
                        $types[] = $t;
                    }
                }
            }
        }

        return $types;
    }

    /**
     * @return array<string, string> dil/bolge kodu (kucuk harf) => href (son gorulen kazanir)
     */
    private function hreflangTags(Crawler $crawler): array
    {
        $tags = [];
        foreach ($crawler->filter('link[rel="alternate"][hreflang]') as $node) {
            $lang = strtolower(trim((string) $node->getAttribute('hreflang')));
            $href = trim((string) $node->getAttribute('href'));
            if ($lang !== '' && $href !== '') {
                $tags[$lang] = $href;
            }
        }

        return $tags;
    }

    /**
     * hreflangTags() ile ayni degil: yinelenen kodlari tespit edebilmek icin
     * dedup edilmeden, dokumanda gorulen sirayla tum kodlari dondurur.
     *
     * @return string[]
     */
    private function hreflangLangCodes(Crawler $crawler): array
    {
        $codes = [];
        foreach ($crawler->filter('link[rel="alternate"][hreflang]') as $node) {
            $lang = strtolower(trim((string) $node->getAttribute('hreflang')));
            if ($lang !== '') {
                $codes[] = $lang;
            }
        }

        return $codes;
    }

    /**
     * @param  array<string, string>  $hreflangTags
     * @param  string[]  $hreflangCodes
     * @return string[]
     */
    private function hreflangIssues(array $hreflangTags, array $hreflangCodes, string $pageUrl): array
    {
        if ($hreflangTags === []) {
            return [];
        }

        $issues = [];

        // ISO 639-1/639-2 dil kodu, istege bagli "-BOLGE" veya "-Betimleme" eki; "x-default" ayrica kabul edilir.
        $validPattern = '/^[a-z]{2,3}(-[a-z]{2}|-[a-z]{4})?$/i';
        foreach (array_keys($hreflangTags) as $lang) {
            if ($lang !== 'x-default' && !preg_match($validPattern, $lang)) {
                $issues[] = sprintf('Geçersiz dil/bölge kodu: %s', $lang);
            }
        }

        foreach (array_count_values($hreflangCodes) as $lang => $count) {
            if ($count > 1) {
                $issues[] = sprintf('Yinelenen hreflang kodu: %s (%d kez)', $lang, $count);
            }
        }

        if (!isset($hreflangTags['x-default'])) {
            $issues[] = "x-default eksik";
        }

        $hasSelfReference = false;
        foreach ($hreflangTags as $lang => $href) {
            if ($lang === 'x-default') {
                continue;
            }

            $absoluteHref = $this->toAbsoluteUrl($href, $pageUrl) ?? $href;
            if ($this->normalizeUrlForComparison($absoluteHref) === $this->normalizeUrlForComparison($pageUrl)) {
                $hasSelfReference = true;
                break;
            }
        }
        if (!$hasSelfReference) {
            $issues[] = 'Sayfa kendi hreflang kümesinde yok (self-reference eksik)';
        }

        return $issues;
    }

    /**
     * @return array{total: int, missing_alt: int, missing_dimensions: int, not_lazy: int, legacy_format: int}
     */
    private function imageStats(Crawler $crawler): array
    {
        $legacyExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];

        $total = 0;
        $missingAlt = 0;
        $missingDimensions = 0;
        $notLazy = 0;
        $legacyFormat = 0;

        foreach ($crawler->filter('img') as $node) {
            $total++;

            if (trim((string) $node->getAttribute('alt')) === '') {
                $missingAlt++;
            }

            $width = trim((string) $node->getAttribute('width'));
            $height = trim((string) $node->getAttribute('height'));
            if ($width === '' && $height === '') {
                $missingDimensions++;
            }

            if (strtolower(trim((string) $node->getAttribute('loading'))) !== 'lazy') {
                $notLazy++;
            }

            $src = $node->getAttribute('src') ?: $node->getAttribute('data-src') ?: '';
            $extension = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            if (in_array($extension, $legacyExtensions, true)) {
                $legacyFormat++;
            }
        }

        return [
            'total' => $total,
            'missing_alt' => $missingAlt,
            'missing_dimensions' => $missingDimensions,
            'not_lazy' => $notLazy,
            'legacy_format' => $legacyFormat,
        ];
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): ?string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        $base = parse_url($baseUrl);
        if (!isset($base['scheme'], $base['host'])) {
            return null;
        }

        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        return $origin . '/' . ltrim($href, '/');
    }
}
