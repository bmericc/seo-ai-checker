<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\CanonicalHost\CanonicalHostChecker;
use App\Services\Crux\CrUxChecker;
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
        private readonly SitemapUrlSync $sitemapUrlSync,
        private readonly KeywordSuggester $keywordSuggester,
    ) {
    }

    public function run(Domain $domain): DomainCheck
    {
        $rootUrl = $domain->rootUrl();

        $robotsTxt = $this->robotsTxtChecker->check($rootUrl);
        $sitemap = $this->sitemapChecker->check($rootUrl);
        $llmsTxt = $this->llmsTxtChecker->check($rootUrl);
        $securityHeaders = $this->securityHeadersChecker->check($domain->domain);

        $canonicalHost = $this->canonicalHostChecker->check($domain->domain);
        $crux = $this->cruxChecker->check('https://' . $canonicalHost->canonicalHost);

        if ($sitemap->found && $sitemap->isValidXml) {
            $this->sitemapUrlSync->sync($domain, $sitemap->urls);
        }

        $excludedPhrases = [
            ...$domain->keywords()->pluck('keyword')->all(),
            ...($domain->dismissed_keyword_suggestions ?? []),
        ];
        $keywordSuggestions = $this->keywordSuggester->suggest($rootUrl, $excludedPhrases);

        return $domain->domainChecks()->create([
            'ai_crawlers' => [
                'url' => $robotsTxt->url,
                'found' => $robotsTxt->found,
                'crawlers' => $robotsTxt->crawlers,
            ],
            'sitemap' => [
                'url' => $sitemap->url,
                'found' => $sitemap->found,
                'is_valid_xml' => $sitemap->isValidXml,
                'is_sitemap_index' => $sitemap->isSitemapIndex,
                'url_count' => $sitemap->urlCount,
                'error' => $sitemap->error,
            ],
            'llms_txt' => [
                'url' => $llmsTxt->url,
                'found' => $llmsTxt->found,
                'preview' => $llmsTxt->preview,
            ],
            'security_headers' => [
                'reachable' => $securityHeaders->reachable,
                'headers' => $securityHeaders->headers,
                'http_redirects_to_https' => $securityHeaders->httpRedirectsToHttps,
            ],
            'canonical_host' => [
                'original_host' => $canonicalHost->originalHost,
                'canonical_host' => $canonicalHost->canonicalHost,
                'redirected' => $canonicalHost->redirected,
                'redirect_status' => $canonicalHost->redirectStatus,
            ],
            'crux' => [
                'configured' => $crux->configured,
                'found' => $crux->found,
                'origin' => $crux->origin,
                'metrics' => $crux->metrics,
                'collection_period' => $crux->collectionPeriod,
                'error' => $crux->error,
            ],
            'suggested_keywords' => array_map(
                static fn (KeywordSuggestion $s) => ['phrase' => $s->phrase, 'score' => $s->score],
                $keywordSuggestions,
            ),
        ]);
    }
}
