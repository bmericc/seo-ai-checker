<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Check;
use App\Models\Keyword;
use App\Services\Eeat\EeatScorer;
use App\Services\Lighthouse\PageSpeedInsightsClient;
use App\Services\Llm\AnthropicVisibilityChecker;
use App\Services\Llm\GeminiVisibilityChecker;
use App\Services\Llm\OpenAiVisibilityChecker;
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
        private readonly OpenAiVisibilityChecker $openAiChecker,
        private readonly AnthropicVisibilityChecker $anthropicChecker,
        private readonly GeminiVisibilityChecker $geminiChecker,
        private readonly EeatScorer $eeatScorer,
    ) {
    }

    public function run(Keyword $keyword): Check
    {
        $domain = $keyword->domain->domain;
        $targetUrl = $keyword->targetUrl();

        $serp = $this->safeSearch($keyword->keyword);

        $onPageData = null;
        $onPageError = null;
        $eeat = null;
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
                'recommended_schema_types' => $onPage->recommendedSchemaTypes,
                'hreflang_tags' => $onPage->hreflangTags,
                'hreflang_issues' => $onPage->hreflangIssues,
                'image_stats' => $onPage->imageStats,
                'author_detected' => $onPage->authorDetected,
                'author_name' => $onPage->authorName,
                'published_date_detected' => $onPage->publishedDateDetected,
                'published_date' => $onPage->publishedDate,
                'about_page_linked' => $onPage->aboutPageLinked,
                'contact_page_linked' => $onPage->contactPageLinked,
            ];

            $eeat = $this->eeatScorer->score(
                $onPage,
                $keyword->domain->fact,
                $keyword->domain->latestDomainCheck?->bing_backlinks,
            );
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
            'llm_visibility' => $this->buildLlmVisibility($keyword, $domain),
            'onpage' => $onPageData,
            'onpage_error' => $onPageError,
            'eeat_score' => $eeat?->score,
            'eeat_breakdown' => $eeat?->toArray(),
            'lighthouse_performance' => $lighthouse->performanceScore,
            'lighthouse_seo' => $lighthouse->seoScore,
            'lighthouse_accessibility' => $lighthouse->accessibilityScore,
            'lighthouse_best_practices' => $lighthouse->bestPracticesScore,
            'lighthouse_error' => $lighthouse->error,
            'lighthouse_raw' => $lighthouse->raw,
        ]);
    }

    /**
     * Domain'de AI gorunurluk kontrolu kapaliysa ya da hicbir saglayici icin
     * API key girilmemisse null doner (hic calistirilmaz, maliyet olusmaz).
     * Yalnizca gercekten yapilandirilmis saglayicilar icin sonuc uretilir.
     *
     * @return ?array<string, array{present: bool, response: ?string, error: ?string}>
     */
    private function buildLlmVisibility(Keyword $keyword, string $domain): ?array
    {
        if (!$keyword->domain->llm_visibility_enabled) {
            return null;
        }

        $apiKeys = $keyword->domain->llmApiKeys->keyBy('provider');
        if ($apiKeys->isEmpty()) {
            return null;
        }

        $results = [];

        if ($apiKeys->has('openai')) {
            $results['openai'] = $this->safeCheckLlmVisibility(
                fn () => $this->openAiChecker->check($keyword->keyword, $domain, $apiKeys['openai']->api_key),
            );
        }

        if ($apiKeys->has('anthropic')) {
            $results['anthropic'] = $this->safeCheckLlmVisibility(
                fn () => $this->anthropicChecker->check($keyword->keyword, $domain, $apiKeys['anthropic']->api_key),
            );
        }

        if ($apiKeys->has('gemini')) {
            $results['gemini'] = $this->safeCheckLlmVisibility(
                fn () => $this->geminiChecker->check($keyword->keyword, $domain, $apiKeys['gemini']->api_key),
            );
        }

        return $results;
    }

    /**
     * @return array{present: bool, response: ?string, error: ?string}
     */
    private function safeCheckLlmVisibility(callable $check): array
    {
        try {
            return $check()->toArray();
        } catch (GuzzleException $e) {
            return ['present' => false, 'response' => null, 'error' => $e->getMessage()];
        }
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
