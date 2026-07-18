<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\Sitemap\SharedSitemapUrlLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedSitemapUrlLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_a_sibling_domains_url_by_matching_domain_string(): void
    {
        $domainA = Domain::query()->create(['domain' => 'example.com']);
        $domainB = Domain::query()->create(['domain' => 'example.com']);

        $sibling = $domainA->sitemapUrls()->create([
            'url' => 'https://example.com/page1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $found = (new SharedSitemapUrlLookup())->siblingsByUrl($domainB, ['https://example.com/page1']);

        $this->assertSame($sibling->id, $found->get('https://example.com/page1')->id);
    }

    public function test_ignores_urls_belonging_to_the_domains_own_row(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $domain->sitemapUrls()->create([
            'url' => 'https://example.com/page1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $found = (new SharedSitemapUrlLookup())->siblingsByUrl($domain, ['https://example.com/page1']);

        $this->assertTrue($found->isEmpty());
    }

    public function test_ignores_urls_for_a_different_domain_string(): void
    {
        $domainA = Domain::query()->create(['domain' => 'other.com']);
        $domainB = Domain::query()->create(['domain' => 'example.com']);

        $domainA->sitemapUrls()->create([
            'url' => 'https://other.com/page1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $found = (new SharedSitemapUrlLookup())->siblingsByUrl($domainB, ['https://other.com/page1']);

        $this->assertTrue($found->isEmpty());
    }

    public function test_lighthouse_freshness_threshold(): void
    {
        $lookup = new SharedSitemapUrlLookup();
        $domain = Domain::query()->create(['domain' => 'example.com']);

        $fresh = $domain->sitemapUrls()->create([
            'url' => 'https://example.com/fresh',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'lighthouse_checked_at' => now()->subHours(5),
        ]);
        $stale = $domain->sitemapUrls()->create([
            'url' => 'https://example.com/stale',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'lighthouse_checked_at' => now()->subHours(7),
        ]);
        $never = $domain->sitemapUrls()->create([
            'url' => 'https://example.com/never',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->assertTrue($lookup->isLighthouseFresh($fresh));
        $this->assertFalse($lookup->isLighthouseFresh($stale));
        $this->assertFalse($lookup->isLighthouseFresh($never));
        $this->assertFalse($lookup->isLighthouseFresh(null));
    }

    public function test_onpage_freshness_threshold(): void
    {
        $lookup = new SharedSitemapUrlLookup();
        $domain = Domain::query()->create(['domain' => 'example.com']);

        $fresh = $domain->sitemapUrls()->create([
            'url' => 'https://example.com/fresh',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'onpage_checked_at' => now()->subHours(1),
        ]);
        $stale = $domain->sitemapUrls()->create([
            'url' => 'https://example.com/stale',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'onpage_checked_at' => now()->subHours(8),
        ]);

        $this->assertTrue($lookup->isOnPageFresh($fresh));
        $this->assertFalse($lookup->isOnPageFresh($stale));
    }
}
