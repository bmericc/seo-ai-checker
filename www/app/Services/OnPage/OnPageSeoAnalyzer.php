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

        $author = $this->detectAuthor($crawler);
        $publishedDate = $this->detectPublishedDate($crawler);
        $aboutContact = $this->detectAboutContactLinks($crawler);
        $recommendedSchemaTypes = $this->recommendSchemaTypes(
            $crawler,
            $schemaTypes,
            $normalizedText,
            $author['detected'],
            $publishedDate['detected'],
            $url,
        );

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
            authorDetected: $author['detected'],
            authorName: $author['name'],
            publishedDateDetected: $publishedDate['detected'],
            publishedDate: $publishedDate['value'],
            aboutPageLinked: $aboutContact['about'],
            contactPageLinked: $aboutContact['contact'],
            recommendedSchemaTypes: $recommendedSchemaTypes,
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
        $types = [];
        foreach ($this->normalizeJsonLdNodes($data) as $node) {
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
     * JSON-LD ya tek bir nesne, ya bir nesne listesi, ya da "@graph"
     * altinda bir nesne listesi olabilir - schema tip cikarimi ve
     * author/date cikarimi ayni normalizasyona ihtiyac duyar.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function normalizeJsonLdNodes(array $data): array
    {
        return isset($data['@graph']) && is_array($data['@graph'])
            ? $data['@graph']
            : (array_is_list($data) ? $data : [$data]);
    }

    /**
     * @return array{detected: bool, name: ?string}
     */
    private function detectAuthor(Crawler $crawler): array
    {
        $metaAuthor = $this->metaContent($crawler, 'author');
        if ($metaAuthor !== null) {
            return ['detected' => true, 'name' => $metaAuthor];
        }

        foreach ($crawler->filter('script[type="application/ld+json"]') as $node) {
            $data = json_decode($node->textContent ?? '', true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($this->normalizeJsonLdNodes($data) as $ldNode) {
                $authorName = $this->extractJsonLdAuthorName($ldNode);
                if ($authorName !== null) {
                    return ['detected' => true, 'name' => $authorName];
                }
            }
        }

        $bylineNode = $crawler->filter('[rel="author"], .author, .byline, [itemprop="author"]');
        if ($bylineNode->count() > 0) {
            $name = trim($bylineNode->first()->text(''));

            return ['detected' => true, 'name' => $name !== '' ? mb_substr($name, 0, 100) : null];
        }

        return ['detected' => false, 'name' => null];
    }

    private function extractJsonLdAuthorName(mixed $node): ?string
    {
        if (!is_array($node) || !isset($node['author'])) {
            return null;
        }

        $author = $node['author'];
        if (is_string($author) && trim($author) !== '') {
            return trim($author);
        }

        if (is_array($author)) {
            // author bir nesne ({"name": "..."}) ya da nesne listesi olabilir.
            $first = array_is_list($author) ? ($author[0] ?? null) : $author;
            if (is_array($first) && isset($first['name']) && is_string($first['name']) && trim($first['name']) !== '') {
                return trim($first['name']);
            }
        }

        return null;
    }

    /**
     * @return array{detected: bool, value: ?string}
     */
    private function detectPublishedDate(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]') as $node) {
            $data = json_decode($node->textContent ?? '', true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($this->normalizeJsonLdNodes($data) as $ldNode) {
                if (!is_array($ldNode)) {
                    continue;
                }

                $date = $ldNode['datePublished'] ?? $ldNode['dateModified'] ?? null;
                if (is_string($date) && trim($date) !== '') {
                    return ['detected' => true, 'value' => trim($date)];
                }
            }
        }

        $metaDate = $this->metaProperty($crawler, 'article:published_time');
        if ($metaDate !== null) {
            return ['detected' => true, 'value' => $metaDate];
        }

        $timeNode = $crawler->filter('time[datetime]');
        if ($timeNode->count() > 0) {
            $datetime = trim((string) $timeNode->first()->attr('datetime'));
            if ($datetime !== '') {
                return ['detected' => true, 'value' => $datetime];
            }
        }

        return ['detected' => false, 'value' => null];
    }

    /**
     * @return array{about: bool, contact: bool}
     */
    private function detectAboutContactLinks(Crawler $crawler): array
    {
        $about = false;
        $contact = false;

        foreach ($crawler->filter('a[href]') as $node) {
            $href = mb_strtolower(trim((string) $node->getAttribute('href')));
            $text = mb_strtolower(trim($node->textContent ?? ''));

            if (!$about && (str_contains($href, '/about') || str_contains($text, 'hakkımızda') || str_contains($text, 'hakkimizda') || str_contains($text, 'about us') || $text === 'about')) {
                $about = true;
            }

            if (!$contact && (str_contains($href, '/contact') || str_contains($href, '/iletisim') || str_contains($text, 'iletişim') || str_contains($text, 'iletisim') || str_contains($text, 'contact'))) {
                $contact = true;
            }

            if ($about && $contact) {
                break;
            }
        }

        return ['about' => $about, 'contact' => $contact];
    }

    /**
     * Eksik olabilecek, sayfa iceriginden makul olcude cikarilabilecek
     * schema.org tiplerini onerir - kesin bir tespit degil, "bu kaliba
     * benziyor" seviyesinde bir sezgiseldir (ornegin gercek FAQ icerigi
     * olmadan sonu "?" ile biten iki baslik da tetikleyebilir).
     *
     * @param  string[]  $existingSchemaTypes
     * @return array<int, array{type: string, reason_key: string, reason_params: array<string, int|string>}>
     */
    private function recommendSchemaTypes(Crawler $crawler, array $existingSchemaTypes, string $bodyText, bool $hasAuthor, bool $hasPublishedDate, string $pageUrl): array
    {
        $recommendations = [];
        $lowerBodyText = mb_strtolower($bodyText);

        if (!in_array('FAQPage', $existingSchemaTypes, true)) {
            $questionHeadings = 0;
            foreach ($crawler->filter('h2, h3, h4') as $node) {
                if (str_ends_with(trim($node->textContent ?? ''), '?')) {
                    $questionHeadings++;
                }
            }
            if ($questionHeadings >= 2) {
                $recommendations[] = ['type' => 'FAQPage', 'reason_key' => 'faq_headings_found', 'reason_params' => ['count' => $questionHeadings]];
            }
        }

        if (!in_array('HowTo', $existingSchemaTypes, true)) {
            $hasStepKeyword = false;
            foreach ($crawler->filter('h1, h2, h3, h4') as $node) {
                $heading = mb_strtolower(trim($node->textContent ?? ''));
                if (str_contains($heading, 'adım') || str_contains($heading, 'nasıl') || str_contains($heading, 'step') || str_contains($heading, 'how to')) {
                    $hasStepKeyword = true;
                    break;
                }
            }
            $orderedListItems = $crawler->filter('ol > li')->count();
            if ($hasStepKeyword && $orderedListItems >= 3) {
                $recommendations[] = ['type' => 'HowTo', 'reason_key' => 'howto_steps_found', 'reason_params' => ['count' => $orderedListItems]];
            }
        }

        if (!in_array('Product', $existingSchemaTypes, true)) {
            $hasPrice = (bool) preg_match('/(₺|\$)\s?\d|\d+[.,]\d{2}\s?(₺|TL|\$)|\b\d+\s?TL\b/u', $lowerBodyText);
            $hasCartIndicator = str_contains($lowerBodyText, 'sepete ekle') || str_contains($lowerBodyText, 'add to cart') || str_contains($lowerBodyText, 'satın al') || str_contains($lowerBodyText, 'buy now');
            if ($hasPrice && $hasCartIndicator) {
                $recommendations[] = ['type' => 'Product', 'reason_key' => 'product_indicators_found', 'reason_params' => []];
            }
        }

        if (!in_array('Review', $existingSchemaTypes, true) && !in_array('AggregateRating', $existingSchemaTypes, true)) {
            $hasRatingPattern = (bool) preg_match('/\b\d(\.\d)?\s?\/\s?5\b|\b\d(\.\d)?\s?\/\s?10\b|yıldız|star-rating/u', $lowerBodyText);
            if ($hasRatingPattern) {
                $recommendations[] = ['type' => 'Review', 'reason_key' => 'rating_indicators_found', 'reason_params' => []];
            }
        }

        if (!in_array('Article', $existingSchemaTypes, true) && !in_array('BlogPosting', $existingSchemaTypes, true) && $hasAuthor && $hasPublishedDate) {
            $recommendations[] = ['type' => 'Article', 'reason_key' => 'author_and_date_present', 'reason_params' => []];
        }

        $path = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
        if (($path === '' || $path === '/') && !in_array('Organization', $existingSchemaTypes, true) && !in_array('WebSite', $existingSchemaTypes, true)) {
            $recommendations[] = ['type' => 'Organization', 'reason_key' => 'homepage_missing_org_schema', 'reason_params' => []];
        }

        return $recommendations;
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
