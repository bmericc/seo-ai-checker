<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OnPage\OnPageSeoAnalyzer;
use App\Services\OnPage\OnPageSeoResult;
use App\Services\Serp\GoogleSerpScraper;
use App\Services\Serp\SerpResult;
use App\Support\ConsoleReporter;
use App\Support\HttpClientFactory;
use App\Support\Keyword\KeywordEntry;
use App\Support\Keyword\KeywordListLoader;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use RuntimeException;

class SeoCheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'seo:check
        {--d|domain= : Takip edilen domain (ornek: example.com)}
        {--k|keyword=* : Kontrol edilecek anahtar kelime (birden fazla kez kullanilabilir)}
        {--f|keywords-file= : Her satirda bir anahtar kelime (istege bagli "kelime|url") iceren dosya}
        {--u|url= : On-page SEO analizi icin varsayilan sayfa (belirtilmezse https://{domain}/ kullanilir)}
        {--skip-onpage : On-page SEO analizini atla, sadece SERP/AI Overview kontrolu yap}
        {--hl= : Google arayuz dili}
        {--gl= : Google bolge kodu}
        {--delay= : Istekler arasi bekleme (ms)}
        {--proxy= : HTTP proxy (opsiyonel)}
        {--user-agent= : Ozel User-Agent (opsiyonel)}
        {--json= : Sonuclari JSON olarak da yaz (dosya yolu)}';

    /**
     * @var string
     */
    protected $description = 'Google SERP siralamasi, AI Overview gorunurlugu ve on-page SEO kontrolu yapar (veritabani kullanmadan, tek seferlik)';

    public function handle(): int
    {
        $domain = trim((string) $this->option('domain'));
        if ($domain === '') {
            $this->components->error('--domain secenegi zorunludur (ornek: --domain=example.com).');

            return self::FAILURE;
        }

        $entries = $this->collectKeywordEntries();
        if ($entries === []) {
            $this->components->error('En az bir anahtar kelime belirtmelisiniz (--keyword veya --keywords-file).');

            return self::FAILURE;
        }

        $serpConfig = config('seo.serp');
        $hl = (string) ($this->option('hl') ?: $serpConfig['hl']);
        $gl = (string) ($this->option('gl') ?: $serpConfig['gl']);
        $delayMs = max(0, (int) ($this->option('delay') ?: $serpConfig['request_delay_ms']));
        $proxy = $this->option('proxy') ?: $serpConfig['proxy'];
        $userAgent = $this->option('user-agent') ?: $serpConfig['user_agent'];
        $skipOnPage = (bool) $this->option('skip-onpage');
        $defaultUrl = $this->option('url');
        $jsonPath = $this->option('json');

        $acceptLanguage = sprintf('%s-%s,%s;q=0.9,en-US;q=0.8,en;q=0.7', $hl, strtoupper($gl), $hl);
        $client = HttpClientFactory::create(
            $acceptLanguage,
            $userAgent !== null ? (string) $userAgent : null,
            $proxy !== null ? (string) $proxy : null,
        );

        $serpScraper = new GoogleSerpScraper($client, $hl, $gl);
        $onPageAnalyzer = new OnPageSeoAnalyzer($client);
        $reporter = new ConsoleReporter();
        $io = $this->getOutput();

        $this->components->info(sprintf('SEO / AI Overview Kontrolu: %s', $domain));
        $this->components->warn(
            'Bu arac Google sonuc sayfasini dogrudan HTTP ile ceker; Google sizi gecici olarak '
            . 'engelleyebilir (CAPTCHA) ve AI Overview genellikle JS ile render edildigi icin '
            . 'her zaman yakalanamayabilir. Sonuclari bu sinirlamalarla birlikte degerlendirin.'
        );

        $jsonReport = [];
        $first = true;

        foreach ($entries as $entry) {
            if (!$first && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
            $first = false;

            $serp = $this->safeSearch($serpScraper, $entry->keyword);
            if ($serp === null) {
                continue;
            }

            $onPage = null;
            if (!$skipOnPage) {
                $targetUrl = $entry->url
                    ?? ($defaultUrl !== null ? (string) $defaultUrl : null)
                    ?? sprintf('https://%s/', $domain);

                $onPage = $this->safeAnalyze($onPageAnalyzer, $targetUrl, $entry->keyword);
            }

            $reporter->report($io, $serp, $domain, $onPage);

            if ($jsonPath !== null) {
                $jsonReport[] = $this->toReportArray($serp, $onPage, $domain);
            }
        }

        if ($jsonPath !== null) {
            file_put_contents(
                (string) $jsonPath,
                json_encode($jsonReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
            );
            $this->components->info(sprintf('JSON rapor yazildi: %s', $jsonPath));
        }

        return self::SUCCESS;
    }

    /**
     * @return KeywordEntry[]
     */
    private function collectKeywordEntries(): array
    {
        $entries = [];

        $keywordsFile = $this->option('keywords-file');
        if ($keywordsFile !== null) {
            try {
                $entries = array_merge($entries, (new KeywordListLoader())->loadFromFile((string) $keywordsFile));
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());
            }
        }

        foreach ((array) $this->option('keyword') as $keyword) {
            if (trim((string) $keyword) !== '') {
                $entries[] = new KeywordEntry(trim((string) $keyword));
            }
        }

        return $entries;
    }

    private function safeSearch(GoogleSerpScraper $scraper, string $keyword): ?SerpResult
    {
        try {
            return $scraper->search($keyword);
        } catch (GuzzleException $e) {
            $this->components->error(sprintf('"%s" icin Google istegi basarisiz: %s', $keyword, $e->getMessage()));

            return null;
        }
    }

    private function safeAnalyze(OnPageSeoAnalyzer $analyzer, string $url, string $keyword): ?OnPageSeoResult
    {
        try {
            return $analyzer->analyze($url, $keyword);
        } catch (GuzzleException $e) {
            $this->components->warn(sprintf('On-page analiz basarisiz (%s): %s', $url, $e->getMessage()));

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toReportArray(SerpResult $serp, ?OnPageSeoResult $onPage, string $domain): array
    {
        return [
            'keyword' => $serp->keyword,
            'blocked' => $serp->blocked,
            'block_reason' => $serp->blockReason,
            'target_domain' => $domain,
            'target_position' => $serp->positionOf($domain),
            'organic_results' => array_map(
                static fn ($r) => ['position' => $r->position, 'url' => $r->url, 'domain' => $r->domain, 'title' => $r->title],
                $serp->organicResults
            ),
            'ai_overview' => [
                'present' => $serp->aiOverview->present,
                'cited_domains' => $serp->aiOverview->citedDomains,
                'target_cited' => $serp->aiOverview->present ? $serp->aiOverview->citesDomain($domain) : null,
                'note' => $serp->aiOverview->note,
            ],
            'on_page' => $onPage === null ? null : [
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
            ],
        ];
    }
}
