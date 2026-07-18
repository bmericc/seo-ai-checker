<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\Bing\BingBacklinksChecker;
use App\Services\CanonicalHost\CanonicalHostChecker;
use App\Services\Crux\CrUxChecker;
use App\Services\Ga4\Ga4Checker;
use App\Services\Google\GoogleTokenService;
use App\Services\Gsc\GscChecker;
use App\Services\Keywords\KeywordSuggester;
use App\Services\Keywords\KeywordSuggestion;
use App\Services\Llms\LlmsTxtChecker;
use App\Services\Robots\RobotsTxtChecker;
use App\Services\Security\SecurityHeadersChecker;
use App\Services\Sitemap\SitemapChecker;
use App\Services\Sitemap\SitemapUrlSync;

/**
 * Belirli bir anahtar kelime/URL'e degil, domain'in tamamina ait kontrolleri
 * (robots.txt AI crawler erisimi, sitemap.xml, llms.txt, guvenlik header'lari)
 * calistirip sonucu veritabanina yazan orkestrasyon servisi. CheckRunner'in
 * domain seviyesindeki karsiligi.
 *
 * ai_crawlers/llms_txt/security_headers/canonical_host/crux/bing_backlinks
 * hangi kullanicinin sordugundan bagimsiz, domain-genelinde sabit
 * gerceklerdir - ayni domain string'ini baska bir kullanici yakin zamanda
 * kontrol ettiyse (bkz. SharedDomainCheckLookup) bu alanlar tekrar
 * taranmaz, o sonuc kopyalanir. Sitemap (SitemapUrl senkronizasyonu icin
 * URL listesi gerekir), GSC/GA4 (kullaniciya ozel OAuth/property) ve
 * anahtar kelime onerileri (kullaniciya ozel disleme listesi) bu paylasima
 * dahil degildir, her zaman taze calisir.
 */
final class DomainCheckRunner
{
    public function __construct(
        private readonly RobotsTxtChecker $robotsTxtChecker,
        private readonly SitemapChecker $sitemapChecker,
        private readonly LlmsTxtChecker $llmsTxtChecker,
        private readonly SecurityHeadersChecker $securityHeadersChecker,
        private readonly CanonicalHostChecker $canonicalHostChecker,
        private readonly CrUxChecker $cruxChecker,
        private readonly GscChecker $gscChecker,
        private readonly Ga4Checker $ga4Checker,
        private readonly BingBacklinksChecker $bingBacklinksChecker,
        private readonly GoogleTokenService $googleTokenService,
        private readonly SitemapUrlSync $sitemapUrlSync,
        private readonly KeywordSuggester $keywordSuggester,
        private readonly SharedDomainCheckLookup $sharedDomainCheckLookup,
    ) {
    }

    public function run(Domain $domain): DomainCheck
    {
        $shared = $this->sharedDomainCheckLookup->find($domain);

        $canonicalHostData = $shared?->canonical_host ?? $this->freshCanonicalHost($domain->domain);
        $canonicalHost = $canonicalHostData['canonical_host'] ?: $domain->domain;
        $rootUrl = sprintf('https://%s/', $canonicalHost);

        $aiCrawlersData = $shared?->ai_crawlers ?? $this->freshAiCrawlers($rootUrl);
        $llmsTxtData = $shared?->llms_txt ?? $this->freshLlmsTxt($rootUrl);
        $securityHeadersData = $shared?->security_headers ?? $this->freshSecurityHeaders($domain->domain);
        $cruxData = $shared?->crux ?? $this->freshCrux($canonicalHost);
        $bingBacklinksData = $shared?->bing_backlinks ?? $this->freshBingBacklinks($rootUrl);

        $sitemap = $this->sitemapChecker->check($rootUrl);
        if ($sitemap->found && $sitemap->isValidXml) {
            $this->sitemapUrlSync->sync($domain, $sitemap->urls);
        }

        $accessToken = $this->googleTokenService->getValidAccessToken($domain->user);
        $gsc = $this->gscChecker->check($rootUrl, $accessToken);
        $ga4 = $this->ga4Checker->check($domain->ga4_property_id, $accessToken);

        $excludedPhrases = [
            ...$domain->keywords()->pluck('keyword')->all(),
            ...($domain->dismissed_keyword_suggestions ?? []),
        ];
        $keywordSuggestions = $this->keywordSuggester->suggest($rootUrl, $excludedPhrases);

        return $domain->domainChecks()->create([
            'ai_crawlers' => $aiCrawlersData,
            'sitemap' => [
                'url' => $sitemap->url,
                'found' => $sitemap->found,
                'is_valid_xml' => $sitemap->isValidXml,
                'is_sitemap_index' => $sitemap->isSitemapIndex,
                'url_count' => $sitemap->urlCount,
                'error' => $sitemap->error,
            ],
            'llms_txt' => $llmsTxtData,
            'security_headers' => $securityHeadersData,
            'canonical_host' => $canonicalHostData,
            'crux' => $cruxData,
            'gsc' => [
                'configured' => $gsc->configured,
                'verified' => $gsc->verified,
                'site_url' => $gsc->siteUrl,
                'clicks' => $gsc->clicks,
                'impressions' => $gsc->impressions,
                'ctr' => $gsc->ctr,
                'average_position' => $gsc->averagePosition,
                'period_start' => $gsc->periodStart,
                'period_end' => $gsc->periodEnd,
                'error' => $gsc->error,
            ],
            'ga4' => [
                'configured' => $ga4->configured,
                'property_id' => $ga4->propertyId,
                'total_sessions' => $ga4->totalSessions,
                'organic_sessions' => $ga4->organicSessions,
                'active_users' => $ga4->activeUsers,
                'period_start' => $ga4->periodStart,
                'period_end' => $ga4->periodEnd,
                'error' => $ga4->error,
            ],
            'bing_backlinks' => $bingBacklinksData,
            'suggested_keywords' => array_map(
                static fn (KeywordSuggestion $s) => ['phrase' => $s->phrase, 'score' => $s->score],
                $keywordSuggestions,
            ),
        ]);
    }

    /**
     * @return array{original_host: string, canonical_host: string, redirected: bool, redirect_status: ?int}
     */
    private function freshCanonicalHost(string $domain): array
    {
        $result = $this->canonicalHostChecker->check($domain);

        return [
            'original_host' => $result->originalHost,
            'canonical_host' => $result->canonicalHost,
            'redirected' => $result->redirected,
            'redirect_status' => $result->redirectStatus,
        ];
    }

    /**
     * @return array{url: string, found: bool, crawlers: array}
     */
    private function freshAiCrawlers(string $rootUrl): array
    {
        $result = $this->robotsTxtChecker->check($rootUrl);

        return [
            'url' => $result->url,
            'found' => $result->found,
            'crawlers' => $result->crawlers,
        ];
    }

    /**
     * @return array{url: string, found: bool, preview: ?string}
     */
    private function freshLlmsTxt(string $rootUrl): array
    {
        $result = $this->llmsTxtChecker->check($rootUrl);

        return [
            'url' => $result->url,
            'found' => $result->found,
            'preview' => $result->preview,
        ];
    }

    /**
     * @return array{reachable: bool, headers: array, http_redirects_to_https: bool}
     */
    private function freshSecurityHeaders(string $domain): array
    {
        $result = $this->securityHeadersChecker->check($domain);

        return [
            'reachable' => $result->reachable,
            'headers' => $result->headers,
            'http_redirects_to_https' => $result->httpRedirectsToHttps,
        ];
    }

    private function freshCrux(string $canonicalHost): array
    {
        $result = $this->cruxChecker->check('https://' . $canonicalHost);

        return [
            'configured' => $result->configured,
            'found' => $result->found,
            'origin' => $result->origin,
            'metrics' => $result->metrics,
            'collection_period' => $result->collectionPeriod,
            'error' => $result->error,
        ];
    }

    private function freshBingBacklinks(string $rootUrl): array
    {
        $result = $this->bingBacklinksChecker->check($rootUrl);

        return [
            'configured' => $result->configured,
            'verified' => $result->verified,
            'site_url' => $result->siteUrl,
            'total_links' => $result->totalLinks,
            'pages_with_links' => $result->pagesWithLinks,
            'top_pages' => $result->topPages,
            'error' => $result->error,
        ];
    }
}
