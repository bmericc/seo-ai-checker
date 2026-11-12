<?php

namespace Tests\Feature;

use App\Jobs\CrawlSiteKeywordCandidates;
use App\Models\Domain;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlSiteKeywordCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private function fakeClient(array $responses): void
    {
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    private function page(string $title): Response
    {
        return new Response(200, [], "<html><head><title>{$title}</title></head><body><h1>{$title}</h1></body></html>");
    }

    private function domainWithUrls(int $count, string $host = 'example.com'): Domain
    {
        $domain = Domain::query()->create(['domain' => $host]);
        for ($i = 1; $i <= $count; $i++) {
            $domain->sitemapUrls()->create([
                'url' => "https://{$host}/sayfa-{$i}",
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }
        $domain->update(['site_keyword_scan' => ['status' => 'running', 'scanned' => 0]]);

        return $domain;
    }

    private function runJob(Domain $domain): void
    {
        $this->app->call([new CrawlSiteKeywordCandidates($domain->id), 'handle']);
    }

    public function test_scans_pages_stores_phrases_and_finishes(): void
    {
        Queue::fake();
        $domain = $this->domainWithUrls(2);
        $this->fakeClient([$this->page('Dernek Muhasebe Yazilimi'), $this->page('Dernek Muhasebe Raporlari')]);

        $this->runJob($domain);

        $domain->refresh();
        $this->assertSame('done', $domain->site_keyword_scan['status']);
        $this->assertSame(2, $domain->site_keyword_scan['scanned']);
        $this->assertSame(2, $domain->site_keyword_scan['total']);
        $this->assertNotEmpty($domain->sitemapUrls()->first()->keyword_phrases);

        $top = collect($domain->site_keyword_suggestions)->firstWhere('phrase', 'dernek muhasebe');
        $this->assertNotNull($top);
        $this->assertSame(2, $top['pages']);
        Queue::assertNothingPushed();
    }

    public function test_requeues_itself_while_pages_remain(): void
    {
        Queue::fake();
        $domain = $this->domainWithUrls(CrawlSiteKeywordCandidates::BATCH_SIZE + 3);
        $this->fakeClient(array_fill(0, CrawlSiteKeywordCandidates::BATCH_SIZE, $this->page('Ornek Sayfa')));

        $this->runJob($domain);

        $domain->refresh();
        $this->assertSame('running', $domain->site_keyword_scan['status']);
        $this->assertSame(CrawlSiteKeywordCandidates::BATCH_SIZE, $domain->site_keyword_scan['scanned']);
        $this->assertSame(3, CrawlSiteKeywordCandidates::pendingQuery($domain)->count());
        Queue::assertPushed(CrawlSiteKeywordCandidates::class, fn ($job) => $job->domainId === $domain->id);
    }

    public function test_failed_page_is_marked_checked_and_does_not_break_the_chain(): void
    {
        Queue::fake();
        $domain = $this->domainWithUrls(3);
        $this->fakeClient([
            new ConnectException('Connection refused', new Request('GET', 'https://example.com/sayfa-1')),
            new Response(404, [], 'Not found'),
            $this->page('Calisan Sayfa'),
        ]);

        $this->runJob($domain);

        $urls = $domain->sitemapUrls()->orderBy('id')->get();
        $this->assertSame([], $urls[0]->keyword_phrases);
        $this->assertSame([], $urls[1]->keyword_phrases);
        $this->assertNotEmpty($urls[2]->keyword_phrases);
        $this->assertTrue($urls->every(fn ($u) => $u->keyword_phrases_checked_at !== null));
        $this->assertSame('done', $domain->fresh()->site_keyword_scan['status']);
    }

    public function test_fresh_phrases_from_another_users_domain_are_copied_without_fetching(): void
    {
        Queue::fake();
        $other = Domain::query()->create(['domain' => 'example.com', 'user_id' => User::factory()->create()->id]);
        $other->sitemapUrls()->create([
            'url' => 'https://example.com/sayfa-1',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'keyword_phrases' => [['phrase' => 'paylasilan ifade', 'score' => 5]],
            'keyword_phrases_checked_at' => now()->subDay(),
        ]);

        $domain = $this->domainWithUrls(1);
        // Bos MockHandler: herhangi bir HTTP istegi atilirsa test patlar.
        $this->fakeClient([]);

        $this->runJob($domain);

        $this->assertSame([['phrase' => 'paylasilan ifade', 'score' => 5]], $domain->sitemapUrls()->first()->keyword_phrases);
        $this->assertSame('paylasilan ifade', $domain->fresh()->site_keyword_suggestions[0]['phrase']);
    }

    public function test_refresh_endpoint_returns_card_html_for_editors_only(): void
    {
        $owner = User::factory()->create(['approved_at' => now()]);
        $stranger = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create([
            'domain' => 'example.com',
            'user_id' => $owner->id,
            'site_keyword_suggestions' => [['phrase' => 'site geneli aday', 'score' => 3.0, 'pages' => 4, 'url' => 'https://example.com/x']],
            'site_keyword_scan' => ['status' => 'running', 'scanned' => 4, 'total' => 10],
        ]);

        $response = $this->actingAs($owner)->getJson(route('domains.keyword-suggestions', $domain));
        $response->assertOk()->assertJson(['running' => true]);
        $this->assertStringContainsString('site geneli aday', $response->json('html'));
        $this->assertStringContainsString('4 / 10', $response->json('html'));

        $this->actingAs($stranger)->getJson(route('domains.keyword-suggestions', $domain))->assertStatus(403);
    }
}
