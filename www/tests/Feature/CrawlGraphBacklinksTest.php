<?php

namespace Tests\Feature;

use App\Models\CrawlGraphSnapshot;
use App\Models\Domain;
use App\Models\User;
use App\Services\CrawlGraph\CrawlGraphClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlGraphBacklinksTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $requests = [];

    private function fakeClient(array $responses, ?string $key = 'cg_test'): void
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));
        $this->app->instance(CrawlGraphClient::class, new CrawlGraphClient(new Client(['handler' => $stack]), $key));
        config(['seo.crawlgraph.api_key' => $key]);
    }

    private function releases(string $latest = 'cc-main-2026-jul-aug-sep'): Response
    {
        return new Response(200, [], json_encode(['releases' => [
            ['id' => 'cc-main-2026-oct-nov-dec', 'label' => 'Oct-Dec 2026', 'available' => false],
            ['id' => $latest, 'label' => 'Jul-Sep 2026', 'available' => true],
            ['id' => 'cc-main-2026-apr-may-jun', 'label' => 'Apr-Jun 2026', 'available' => true],
        ]]));
    }

    private function backlinks(array $domains, string $release = 'cc-main-2026-jul-aug-sep'): Response
    {
        return new Response(200, [], json_encode([
            'domain' => 'example.com', 'release_id' => $release, 'release_label' => 'Jul-Sep 2026',
            'total_linking_domains' => count($domains), 'returned' => count($domains), 'cg_authority' => 6, 'cg_rank' => 5767988,
            'results' => array_map(fn ($d) => ['linking_domain' => $d, 'num_hosts' => 2, 'tld' => 'com', 'cg_authority' => 20, 'cg_rank' => 1], $domains),
        ]));
    }

    private function user(): User
    {
        return User::factory()->create(['approved_at' => now()]);
    }

    public function test_sync_stores_linking_domains_for_latest_release(): void
    {
        $user = $this->user();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $this->fakeClient([$this->releases(), $this->backlinks(['gencgazete.net', 'fitveform.com'])]);

        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();

        $snapshot = $domain->crawlGraphSnapshots()->sole();
        $this->assertSame('cc-main-2026-jul-aug-sep', $snapshot->release_id);
        $this->assertSame(2, $snapshot->total_linking_domains);
        $this->assertSame(6, $snapshot->authority);
        $this->assertSame('gencgazete.net', $snapshot->linking_domains[0]['domain']);
        $this->assertNull($snapshot->added_domains);

        $body = json_decode((string) $this->requests[1]['request']->getBody(), true);
        $this->assertSame(['domain' => 'example.com', 'release_id' => 'cc-main-2026-jul-aug-sep', 'limit' => 1000, 'sort' => 'authority'], $body);
        $this->assertSame('Bearer cg_test', $this->requests[1]['request']->getHeaderLine('Authorization'));

        $this->actingAs($user)->get(route('domains.show', $domain))->assertOk()->assertSee('gencgazete.net')->assertSee('Jul-Sep 2026');
    }

    public function test_already_synced_domain_is_not_queried_again(): void
    {
        $user = $this->user();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->crawlGraphSnapshots()->create(['release_id' => 'cc-main-2026-jul-aug-sep', 'linking_domains' => [], 'fetched_at' => now()]);
        $this->fakeClient([$this->releases()]);

        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();

        $this->assertCount(1, $this->requests);
    }

    public function test_new_release_computes_added_and_removed_domains(): void
    {
        $user = $this->user();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->crawlGraphSnapshots()->create([
            'release_id' => 'cc-main-2026-apr-may-jun',
            'linking_domains' => [['domain' => 'old.com', 'hosts' => 1, 'tld' => 'com', 'authority' => 5], ['domain' => 'stay.com', 'hosts' => 1, 'tld' => 'com', 'authority' => 5]],
            'fetched_at' => now()->subMonths(3),
        ]);
        $this->fakeClient([$this->releases(), $this->backlinks(['stay.com', 'fresh.com'])]);

        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();

        $latest = $domain->crawlGraphSnapshots()->where('release_id', 'cc-main-2026-jul-aug-sep')->sole();
        $this->assertSame(['fresh.com'], array_column($latest->added_domains, 'domain'));
        $this->assertSame(['old.com'], array_column($latest->removed_domains, 'domain'));
    }

    public function test_quota_exhaustion_stops_the_run(): void
    {
        $user = $this->user();
        $first = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $second = Domain::query()->create(['domain' => 'second.com', 'user_id' => $user->id]);
        $this->fakeClient([
            $this->releases(),
            new Response(429, [], json_encode(['error' => 'quota_exceeded', 'message' => 'Monthly quota exceeded'])),
        ]);

        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();

        $this->assertCount(2, $this->requests);
        $this->assertSame(0, CrawlGraphSnapshot::count());
    }

    public function test_failed_lookup_is_stored_and_retried_later(): void
    {
        $user = $this->user();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $this->fakeClient([
            $this->releases(),
            new Response(500, [], json_encode(['error' => 'internal_error', 'message' => 'Boom'])),
        ]);
        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();
        $this->assertStringContainsString('Boom', $domain->crawlGraphSnapshots()->sole()->error);

        $this->fakeClient([$this->releases(), $this->backlinks(['a.com'])]);
        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();

        $snapshot = $domain->crawlGraphSnapshots()->sole();
        $this->assertNull($snapshot->error);
        $this->assertSame(1, $snapshot->total_linking_domains);
    }

    public function test_sync_is_skipped_without_api_key(): void
    {
        $user = $this->user();
        Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $this->fakeClient([], key: null);

        $this->artisan('backlinks:sync-crawlgraph')->assertSuccessful();

        $this->assertCount(0, $this->requests);
    }

    public function test_domain_page_lists_a_domain_from_both_sources_once(): void
    {
        $user = $this->user();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->backlinkSnapshots()->create([
            'backlinks' => 10, 'referring_domains' => 2, 'summary' => [],
            'top_referring_domains' => [['domain' => 'www.shared.com', 'rank' => 300, 'backlinks' => 4, 'nofollow' => false, 'spam_score' => 1, 'first_seen' => '2026-01-01', 'lost_date' => null]],
            'anchors' => [], 'new_referring_domains' => [], 'lost_referring_domains' => [],
            'fetched_at' => now(),
        ]);
        $domain->crawlGraphSnapshots()->create([
            'release_id' => 'cc-main-2026-jul-aug-sep',
            'linking_domains' => [['domain' => 'shared.com', 'hosts' => 2, 'tld' => 'com', 'authority' => 25], ['domain' => 'cc-only.com', 'hosts' => 1, 'tld' => 'com', 'authority' => 9]],
            'fetched_at' => now(),
        ]);
        config(['seo.crawlgraph.api_key' => 'cg_test']);

        $html = $this->actingAs($user)->get(route('domains.show', $domain))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'href="https://shared.com"'));
        $this->assertSame(0, substr_count($html, 'href="https://www.shared.com"'));
        $this->assertSame(1, substr_count($html, 'href="https://cc-only.com"'));
    }

    public function test_domain_page_lists_bing_linking_domains_from_latest_site_check(): void
    {
        $user = $this->user();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->domainChecks()->create([
            'bing_backlinks' => ['configured' => true, 'verified' => true, 'referring_domains' => [
                ['domain' => 'bing-linker.com', 'links' => 2, 'anchor' => 'harika araç', 'url' => 'https://bing-linker.com/yazi', 'target' => 'https://example.com/'],
            ]],
        ]);

        $html = $this->actingAs($user)->get(route('domains.show', $domain))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'href="https://bing-linker.com"'));
        $this->assertStringContainsString('href="https://bing-linker.com/yazi"', $html);
        $this->assertStringContainsString('harika araç', $html);
        $this->assertStringContainsString('>BING</span>', $html);
    }
}
