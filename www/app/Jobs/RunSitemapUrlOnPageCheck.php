<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SitemapUrl;
use App\Services\OnPage\OnPageSeoAnalyzer;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Bir sitemap URL'i icin on-page SEO analizini (title/meta, canonical
 * self-reference, H1/baslik hiyerarsisi, Open Graph, Twitter Card,
 * Schema.org tipleri) calistirip sonucu SitemapUrl kaydina yazar.
 * Lighthouse taramasindan ayri bir is olarak kuyruklanir - PSI'nin aksine
 * kendi HTTP + DOM analizimiz saniyeler icinde biter, bu yuzden cok daha
 * genis toplu calismalari destekleyebilir.
 */
final class RunSitemapUrlOnPageCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly int $sitemapUrlId)
    {
    }

    public function handle(OnPageSeoAnalyzer $analyzer): void
    {
        $sitemapUrl = SitemapUrl::find($this->sitemapUrlId);

        if ($sitemapUrl === null || $sitemapUrl->removed_at !== null) {
            return;
        }

        try {
            $result = $analyzer->analyze($sitemapUrl->url);

            $sitemapUrl->update([
                'onpage_data' => [
                    'title' => $result->title,
                    'title_length' => $result->titleLength(),
                    'meta_description' => $result->metaDescription,
                    'meta_description_length' => $result->descriptionLength(),
                    'canonical' => $result->canonical,
                    'canonical_status' => $result->canonicalStatus,
                    'h1_count' => $result->h1Count(),
                    'heading_hierarchy_skip' => $result->headingHierarchySkip,
                    'og_tags' => $result->ogTags,
                    'twitter_card' => $result->twitterCard,
                    'schema_types' => $result->schemaTypes,
                    'deprecated_schema_types' => $result->deprecatedSchemaTypes,
                    'images_missing_alt' => $result->imagesMissingAlt,
                ],
                'onpage_error' => null,
                'onpage_checked_at' => now(),
            ]);
        } catch (GuzzleException $e) {
            $sitemapUrl->update([
                'onpage_error' => $e->getMessage(),
                'onpage_checked_at' => now(),
            ]);
        }
    }
}
