<?php

declare(strict_types=1);

namespace App\Services\Competitors;

use App\Jobs\CrawlSiteKeywordCandidates;
use App\Services\Keywords\KeywordSuggester;
use App\Services\Keywords\SiteKeywordAggregator;
use App\Services\Sitemap\SitemapChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * DataForSEO yoksa rakibin "one cikardigi" ifadeleri ucretsiz cikarir:
 * ana sayfa + sitemap'ten ilk MAX_PAGES sayfa taranir, kendi sitemiz icin
 * kullanilan ayni KeywordSuggester/SiteKeywordAggregator hattindan
 * gecirilir. Siralama/hacim verisi yoktur - sadece "rakip sitesinde bu
 * konuyu islemis" sinyalidir.
 */
class CompetitorSiteCrawler
{
    public const MAX_PAGES = 15;

    private const TIME_BUDGET_SECONDS = 120;

    private const REQUEST_GAP_MICROSECONDS = 500_000;

    public function __construct(
        private readonly Client $client,
        private readonly KeywordSuggester $suggester,
        private readonly SiteKeywordAggregator $aggregator,
        private readonly SitemapChecker $sitemapChecker,
    ) {
    }

    /**
     * @return list<array{keyword: string, position: ?int, search_volume: ?int, url: ?string, score: ?float}>
     */
    public function crawl(string $rootUrl): array
    {
        $homepage = $this->suggester->suggest($rootUrl);

        $pages = [];
        $started = microtime(true);
        $sitemap = $this->sitemapChecker->check($rootUrl);

        foreach (array_slice($sitemap->urls, 0, self::MAX_PAGES) as $url) {
            if (microtime(true) - $started > self::TIME_BUDGET_SECONDS) {
                break;
            }
            if (rtrim($url, '/') === rtrim($rootUrl, '/')) {
                continue;
            }

            usleep(self::REQUEST_GAP_MICROSECONDS);
            $html = $this->fetchHtml($url);
            if ($html !== null) {
                $pages[$url] = CrawlSiteKeywordCandidates::phrasesFromHtml($this->suggester, $html, $url);
            }
        }

        $keywords = [];
        foreach ($this->aggregator->aggregate($homepage, $pages) as $suggestion) {
            $keywords[] = [
                'keyword' => $suggestion->phrase,
                'position' => null,
                'search_volume' => null,
                'url' => $suggestion->url ?? $rootUrl,
                'score' => round($suggestion->score, 4),
            ];
        }

        return $keywords;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
        } catch (GuzzleException) {
            return null;
        }

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && !str_contains($contentType, 'html')) {
            return null;
        }

        return (string) $response->getBody();
    }
}
