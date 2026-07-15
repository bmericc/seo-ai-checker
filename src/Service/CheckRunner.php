<?php

declare(strict_types=1);

namespace SeoAiChecker\Service;

use GuzzleHttp\Exception\GuzzleException;
use SeoAiChecker\Lighthouse\PageSpeedInsightsClient;
use SeoAiChecker\OnPage\OnPageSeoAnalyzer;
use SeoAiChecker\Repository\CheckRepository;
use SeoAiChecker\Serp\AiOverviewResult;
use SeoAiChecker\Serp\GoogleSerpScraper;
use SeoAiChecker\Serp\SerpResult;

/**
 * SERP/AI Overview, on-page SEO ve Lighthouse (PSI) kontrollerini tek bir
 * calistirmada birlestirip sonucu veritabanina yazan orkestrasyon servisi.
 */
final class CheckRunner
{
    public function __construct(
        private readonly GoogleSerpScraper $serpScraper,
        private readonly OnPageSeoAnalyzer $onPageAnalyzer,
        private readonly PageSpeedInsightsClient $lighthouseClient,
        private readonly CheckRepository $checks,
    ) {
    }

    /**
     * @param array<string, mixed> $keywordRow keywords tablosundan bir satir (id, keyword, url, domain_id)
     */
    public function run(array $keywordRow, string $domain): int
    {
        $keyword = (string) $keywordRow['keyword'];
        $targetUrl = $keywordRow['url'] !== null && $keywordRow['url'] !== ''
            ? (string) $keywordRow['url']
            : sprintf('https://%s/', $domain);

        $serp = $this->safeSearch($keyword);

        $onPageJson = null;
        $onPageError = null;
        try {
            $onPage = $this->onPageAnalyzer->analyze($targetUrl, $keyword);
            $onPageJson = json_encode([
                'url' => $onPage->url,
                'title' => $onPage->title,
                'title_length' => $onPage->titleLength(),
                'meta_description' => $onPage->metaDescription,
                'meta_description_length' => $onPage->descriptionLength(),
                'canonical' => $onPage->canonical,
                'meta_robots' => $onPage->metaRobots,
                'h1s' => $onPage->h1s,
                'h2_count' => $onPage->h2Count,
                'word_count' => $onPage->wordCount,
                'keyword_density_percent' => $onPage->keywordDensityPercent,
                'keyword_in_title' => $onPage->keywordInTitle,
                'keyword_in_h1' => $onPage->keywordInH1,
                'keyword_in_description' => $onPage->keywordInDescription,
                'images_missing_alt' => $onPage->imagesMissingAlt,
                'internal_links' => $onPage->internalLinks,
                'external_links' => $onPage->externalLinks,
                'has_structured_data' => $onPage->hasStructuredData,
                'fetch_time_ms' => $onPage->fetchTimeMs,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (GuzzleException $e) {
            $onPageError = sprintf('On-page analiz basarisiz: %s', $e->getMessage());
        }

        $lighthouse = $this->lighthouseClient->analyze($targetUrl);

        $checkId = $this->checks->create([
            'keyword_id' => $keywordRow['id'],
            'blocked' => $serp->blocked,
            'block_reason' => $serp->blockReason,
            'target_position' => $serp->positionOf($domain),
            'organic_results_json' => json_encode(array_map(
                static fn ($r) => ['position' => $r->position, 'url' => $r->url, 'domain' => $r->domain, 'title' => $r->title],
                $serp->organicResults
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ai_overview_present' => $serp->aiOverview->present,
            'ai_overview_cited_domains_json' => json_encode($serp->aiOverview->citedDomains, JSON_UNESCAPED_UNICODE),
            'ai_overview_target_cited' => $serp->aiOverview->present ? $serp->aiOverview->citesDomain($domain) : null,
            'ai_overview_note' => $serp->aiOverview->note,
            'onpage_json' => $onPageJson,
            'onpage_error' => $onPageError,
            'lighthouse_performance' => $lighthouse->performanceScore,
            'lighthouse_seo' => $lighthouse->seoScore,
            'lighthouse_accessibility' => $lighthouse->accessibilityScore,
            'lighthouse_best_practices' => $lighthouse->bestPracticesScore,
            'lighthouse_error' => $lighthouse->error,
        ]);

        return $checkId;
    }

    private function safeSearch(string $keyword): SerpResult
    {
        try {
            return $this->serpScraper->search($keyword);
        } catch (GuzzleException $e) {
            return new SerpResult(
                keyword: $keyword,
                organicResults: [],
                aiOverview: new AiOverviewResult(present: false),
                blocked: true,
                blockReason: sprintf('Google istegine ulasilamadi: %s', $e->getMessage()),
            );
        }
    }
}
