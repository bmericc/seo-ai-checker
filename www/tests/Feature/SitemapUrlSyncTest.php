<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\Sitemap\SharedSitemapUrlLookup;
use App\Services\Sitemap\SitemapUrlSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapUrlSyncTest extends TestCase
{
    use RefreshDatabase;

    private function sync(): SitemapUrlSync
    {
        return new SitemapUrlSync(new SharedSitemapUrlLookup());
    }

    public function test_new_urls_are_created(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);

        $this->sync()->sync($domain, ['https://example.com/', 'https://example.com/about']);

        $this->assertSame(2, $domain->sitemapUrls()->count());
        $this->assertSame(0, $domain->sitemapUrls()->whereNotNull('removed_at')->count());
    }

    public function test_urls_missing_from_a_later_run_are_marked_removed(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $sync = $this->sync();

        $sync->sync($domain, ['https://example.com/', 'https://example.com/about']);
        $sync->sync($domain, ['https://example.com/']);

        $removed = $domain->sitemapUrls()->where('url', 'https://example.com/about')->first();
        $kept = $domain->sitemapUrls()->where('url', 'https://example.com/')->first();

        $this->assertNotNull($removed->removed_at);
        $this->assertNull($kept->removed_at);
    }

    public function test_a_previously_removed_url_reappearing_clears_removed_at(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $sync = $this->sync();

        $sync->sync($domain, ['https://example.com/', 'https://example.com/about']);
        $sync->sync($domain, ['https://example.com/']);
        $sync->sync($domain, ['https://example.com/', 'https://example.com/about']);

        $reappeared = $domain->sitemapUrls()->where('url', 'https://example.com/about')->first();

        $this->assertNull($reappeared->removed_at);
    }

    public function test_first_seen_at_does_not_change_on_subsequent_runs(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $sync = $this->sync();

        $sync->sync($domain, ['https://example.com/']);
        $firstSeen = $domain->sitemapUrls()->first()->first_seen_at;

        $this->travel(1)->hours();
        $sync->sync($domain, ['https://example.com/']);

        $row = $domain->sitemapUrls()->first();
        $this->assertTrue($firstSeen->equalTo($row->first_seen_at));
        $this->assertTrue($row->last_seen_at->greaterThan($firstSeen));
    }

    public function test_new_url_copies_scan_results_from_a_sibling_domain(): void
    {
        $domainA = Domain::query()->create(['domain' => 'example.com']);
        $domainB = Domain::query()->create(['domain' => 'example.com']);

        $domainA->sitemapUrls()->create([
            'url' => 'https://example.com/page1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'lighthouse_performance' => 88,
            'lighthouse_seo' => 95,
            'lighthouse_checked_at' => now(),
            'onpage_data' => ['canonical_status' => 'self'],
            'onpage_checked_at' => now(),
        ]);

        $this->sync()->sync($domainB, ['https://example.com/page1']);

        $copied = $domainB->sitemapUrls()->where('url', 'https://example.com/page1')->first();

        $this->assertSame(88, $copied->lighthouse_performance);
        $this->assertSame(95, $copied->lighthouse_seo);
        $this->assertNotNull($copied->lighthouse_checked_at);
        $this->assertSame('self', $copied->onpage_data['canonical_status']);
        $this->assertNotNull($copied->onpage_checked_at);
    }

    public function test_new_url_with_no_sibling_data_stays_unchecked(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);

        $this->sync()->sync($domain, ['https://example.com/fresh']);

        $row = $domain->sitemapUrls()->where('url', 'https://example.com/fresh')->first();

        $this->assertNull($row->lighthouse_checked_at);
        $this->assertNull($row->onpage_checked_at);
    }
}
