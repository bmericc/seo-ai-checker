<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\Sitemap\SitemapUrlSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapUrlSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_urls_are_created(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);

        (new SitemapUrlSync())->sync($domain, ['https://example.com/', 'https://example.com/about']);

        $this->assertSame(2, $domain->sitemapUrls()->count());
        $this->assertSame(0, $domain->sitemapUrls()->whereNotNull('removed_at')->count());
    }

    public function test_urls_missing_from_a_later_run_are_marked_removed(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $sync = new SitemapUrlSync();

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
        $sync = new SitemapUrlSync();

        $sync->sync($domain, ['https://example.com/', 'https://example.com/about']);
        $sync->sync($domain, ['https://example.com/']);
        $sync->sync($domain, ['https://example.com/', 'https://example.com/about']);

        $reappeared = $domain->sitemapUrls()->where('url', 'https://example.com/about')->first();

        $this->assertNull($reappeared->removed_at);
    }

    public function test_first_seen_at_does_not_change_on_subsequent_runs(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $sync = new SitemapUrlSync();

        $sync->sync($domain, ['https://example.com/']);
        $firstSeen = $domain->sitemapUrls()->first()->first_seen_at;

        $this->travel(1)->hours();
        $sync->sync($domain, ['https://example.com/']);

        $row = $domain->sitemapUrls()->first();
        $this->assertTrue($firstSeen->equalTo($row->first_seen_at));
        $this->assertTrue($row->last_seen_at->greaterThan($firstSeen));
    }
}
