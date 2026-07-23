<?php

namespace App\Providers;

use App\Services\Bing\BingBacklinksChecker;
use App\Services\Bing\BingTokenService;
use App\Services\CanonicalHost\CanonicalHostChecker;
use App\Services\Crux\CrUxChecker;
use App\Services\Ga4\Ga4Checker;
use App\Services\Ga4\Ga4PropertyLister;
use App\Services\Google\GoogleTokenService;
use App\Services\Gsc\GscChecker;
use App\Services\Keywords\KeywordSuggester;
use App\Services\Lighthouse\PageSpeedInsightsClient;
use App\Services\Llms\LlmsTxtChecker;
use App\Services\OnPage\OnPageSeoAnalyzer;
use App\Services\Robots\RobotsTxtChecker;
use App\Services\Serp\GoogleRequestThrottle;
use App\Services\Security\SecurityHeadersChecker;
use App\Services\Serp\GoogleSerpScraper;
use App\Services\SharedDomainCheckLookup;
use App\Services\Sitemap\SharedSitemapUrlLookup;
use App\Services\Sitemap\SitemapChecker;
use App\Services\Sitemap\SitemapUrlSync;
use App\Services\Whois\WhoisChecker;
use App\Support\HttpClientFactory;
use BahriCanli\DomainHunter\DomainParser;
use BahriCanli\DomainHunter\WhoisService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $serp = config('seo.serp');
            $acceptLanguage = sprintf(
                '%s-%s,%s;q=0.9,en-US;q=0.8,en;q=0.7',
                $serp['hl'],
                strtoupper($serp['gl']),
                $serp['hl']
            );

            return HttpClientFactory::create($acceptLanguage, $serp['user_agent'], $serp['proxy']);
        });

        $this->app->singleton(GoogleSerpScraper::class, function ($app) {
            $serp = config('seo.serp');

            return new GoogleSerpScraper(
                $app->make(Client::class),
                $app->make(GoogleRequestThrottle::class),
                $serp['hl'],
                $serp['gl'],
            );
        });

        $this->app->singleton(GoogleRequestThrottle::class, function ($app) {
            $serp = config('seo.serp');

            return new GoogleRequestThrottle($app->make('cache'), $serp['request_delay_ms']);
        });

        $this->app->singleton(OnPageSeoAnalyzer::class, fn ($app) => new OnPageSeoAnalyzer($app->make(Client::class)));

        $this->app->singleton(RobotsTxtChecker::class, fn ($app) => new RobotsTxtChecker($app->make(Client::class)));

        $this->app->singleton(SitemapChecker::class, fn ($app) => new SitemapChecker($app->make(Client::class)));

        $this->app->singleton(LlmsTxtChecker::class, fn ($app) => new LlmsTxtChecker($app->make(Client::class)));

        $this->app->singleton(SecurityHeadersChecker::class, fn ($app) => new SecurityHeadersChecker($app->make(Client::class)));

        $this->app->singleton(CanonicalHostChecker::class, fn ($app) => new CanonicalHostChecker($app->make(Client::class)));

        $this->app->singleton(CrUxChecker::class, fn ($app) => new CrUxChecker($app->make(Client::class), config('seo.crux.api_key')));

        $this->app->singleton(GscChecker::class, fn ($app) => new GscChecker($app->make(Client::class)));

        $this->app->singleton(Ga4Checker::class, fn ($app) => new Ga4Checker($app->make(Client::class)));

        $this->app->singleton(Ga4PropertyLister::class, fn ($app) => new Ga4PropertyLister($app->make(Client::class)));

        $this->app->singleton(BingBacklinksChecker::class, fn ($app) => new BingBacklinksChecker($app->make(Client::class)));

        $this->app->singleton(BingTokenService::class, fn ($app) => new BingTokenService(
            $app->make(Client::class),
            config('services.bing.client_id'),
            config('services.bing.client_secret'),
            config('app.url'),
        ));

        $this->app->singleton(GoogleTokenService::class, fn ($app) => new GoogleTokenService(
            $app->make(Client::class),
            config('services.google.client_id'),
            config('services.google.client_secret'),
        ));

        $this->app->singleton(SharedSitemapUrlLookup::class, fn () => new SharedSitemapUrlLookup());

        $this->app->singleton(SitemapUrlSync::class, fn ($app) => new SitemapUrlSync($app->make(SharedSitemapUrlLookup::class)));

        $this->app->singleton(SharedDomainCheckLookup::class, fn () => new SharedDomainCheckLookup());

        $this->app->singleton(KeywordSuggester::class, fn ($app) => new KeywordSuggester($app->make(Client::class)));

        $this->app->singleton(PageSpeedInsightsClient::class, function () {
            $lh = config('seo.lighthouse');

            return new PageSpeedInsightsClient($lh['psi_api_key'], $lh['psi_strategy']);
        });

        $this->app->singleton(WhoisService::class, fn () => new WhoisService());

        $this->app->singleton(DomainParser::class, fn ($app) => new DomainParser($app->make(WhoisService::class)));

        $this->app->singleton(WhoisChecker::class, fn ($app) => new WhoisChecker(
            $app->make(DomainParser::class),
            $app->make(WhoisService::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
