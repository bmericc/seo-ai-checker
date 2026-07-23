<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Check;
use App\Models\Keyword;
use App\Services\Lighthouse\PageSpeedInsightsClient;
use App\Services\OnPage\OnPageSeoAnalyzer;
use App\Services\Serp\AiOverviewResult;
use App\Services\Serp\SerpResult;
use App\Services\Serp\SerpScraper;
use GuzzleHttp\Exception\GuzzleException;

/**
 * SERP/AI Overview, on-page SEO ve Lighthouse (PSI) kontrollerini tek bir
 * calistirmada birlestirip sonucu veritabanina yazan orkestrasyon servisi.
 */
final class CheckRunner
{
    public function __construct(
        private readonly SerpScraper $serpScraper,
        private readonly OnPageSeoAnalyzer $onPageAnalyzer,
        private readonly PageSpeedInsightsClient $lighthouseClient,
    ) {
    }

    public function run(Keyword $keyword): Check
    {
        $domain = $keyword->domain->domain;
        $targetUrl = $keyword->targetUrl();

        $serp = $this->safeSearch($keyword->keyword);

        $onPageData = null;
        $onPageError = null;
        try {
            $onPage = $this->onPageAnalyzer->analyze($targetUrl, $keyword->keyword);
            $onPageData = [
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
                'canonical_status' => $onPage->canonicalStatus,
                'heading_hierarchy_skip' => $onPage->headingHierarchySkip,
                'og_tags' => $onPage->ogTags,
                'twitter_card' => $onPage->twitterCard,
                'schema_types' => $onPage->schemaTypes,
                'deprecated_schema_types' => $onPage->deprecatedSchemaTypes,
                'hreflang_tags' => $onPage->hreflangTags,
                'hreflang_issues' => $onPage->hreflangIssues,
                'image_stats' => $onPage->imageStats,
            ];
        } catch (GuzzleException $e) {
            $onPageError = sprintf('On-page analiz basarisiz: %s', $e->getMessage());
        }

        $lighthouse = $this->lighthouseClient->analyze($targetUrl);

        return $keyword->checks()->create([
            'blocked' => $serp->blocked,
            'block_reason' => $serp->blockReason,
            'target_position' => $serp->positionOf($domain),
            'organic_results' => array_map(
                static fn ($r) => ['position' => $r->position, 'url' => $r->url, 'domain' => $r->domain, 'title' => $r->title],
                $serp->organicResults
            ),
            'ai_overview_present' => $serp->aiOverview->present,
            'ai_overview_cited_domains' => $serp->aiOverview->citedDomains,
            'ai_overview_target_cited' => $serp->aiOverview->present ? $serp->aiOverview->citesDomain($domain) : null,
            'ai_overview_note' => $serp->aiOverview->note,
            'onpage' => $onPageData,
            'onpage_error' => $onPageError,
            'lighthouse_performance' => $lighthouse->performanceScore,
            'lighthouse_seo' => $lighthouse->seoScore,
            'lighthouse_accessibility' => $lighthouse->accessibilityScore,
            'lighthouse_best_practices' => $lighthouse->bestPracticesScore,
            'lighthouse_error' => $lighthouse->error,
            'lighthouse_raw' => $lighthouse->raw,
        ]);
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
