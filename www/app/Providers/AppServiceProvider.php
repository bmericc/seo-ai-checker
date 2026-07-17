<?php

namespace App\Providers;

use App\Services\Lighthouse\PageSpeedInsightsClient;
use App\Services\OnPage\OnPageSeoAnalyzer;
use App\Services\Robots\RobotsTxtChecker;
use App\Services\Serp\GoogleSerpScraper;
use App\Support\HttpClientFactory;
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

            return new GoogleSerpScraper($app->make(Client::class), $serp['hl'], $serp['gl']);
        });

        $this->app->singleton(OnPageSeoAnalyzer::class, fn ($app) => new OnPageSeoAnalyzer($app->make(Client::class)));

        $this->app->singleton(RobotsTxtChecker::class, fn ($app) => new RobotsTxtChecker($app->make(Client::class)));

        $this->app->singleton(PageSpeedInsightsClient::class, function () {
            $lh = config('seo.lighthouse');

            return new PageSpeedInsightsClient($lh['psi_api_key'], $lh['psi_strategy']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
