<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function fakeHttp(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
    }

    private function ownerWithDomain(): array
    {
        $owner = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);

        return [$owner, $domain];
    }

    public function test_key_file_download_creates_a_key_once(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();

        $first = $this->actingAs($owner)->get(route('domains.indexnow.key-file', $domain));
        $first->assertOk();
        $key = $domain->fresh()->indexnow_key;
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $key);
        $this->assertSame($key, $first->getContent());
        $this->assertStringContainsString($key . '.txt', $first->headers->get('Content-Disposition'));

        $this->actingAs($owner)->get(route('domains.indexnow.key-file', $domain));
        $this->assertSame($key, $domain->fresh()->indexnow_key);
    }

    public function test_verify_marks_domain_verified_when_key_file_matches(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $domain->forceFill(['indexnow_key' => 'abc123abc123abc1'])->save();
        $this->fakeHttp([new Response(200, [], "abc123abc123abc1\n")]);

        $this->actingAs($owner)->post(route('domains.indexnow.verify', $domain))->assertRedirect();

        $this->assertNotNull($domain->fresh()->indexnow_verified_at);
        $this->assertSame('https://example.com/abc123abc123abc1.txt', (string) $this->history[0]['request']->getUri());
    }

    public function test_verify_fails_when_key_file_content_differs(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $domain->forceFill(['indexnow_key' => 'abc123abc123abc1', 'indexnow_verified_at' => now()])->save();
        $this->fakeHttp([new Response(200, [], 'something else')]);

        $this->actingAs($owner)->post(route('domains.indexnow.verify', $domain));

        $this->assertNull($domain->fresh()->indexnow_verified_at);
    }

    public function test_manual_submission_sends_only_same_host_urls(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $domain->forceFill(['indexnow_key' => 'abc123abc123abc1'])->save();
        $this->fakeHttp([new Response(200)]);

        $this->actingAs($owner)->post(route('domains.indexnow.submit', $domain), [
            'source' => 'manual',
            'urls' => "https://example.com/a\nhttps://other.com/b\nhttps://example.com/a\nhttps://example.com/c",
        ])->assertRedirect();

        $sent = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('https://api.indexnow.org/indexnow', (string) $this->history[0]['request']->getUri());
        $this->assertSame('example.com', $sent['host']);
        $this->assertSame('abc123abc123abc1', $sent['key']);
        $this->assertSame('https://example.com/abc123abc123abc1.txt', $sent['keyLocation']);
        $this->assertSame(['https://example.com/a', 'https://example.com/c'], $sent['urlList']);

        $submission = $domain->indexNowSubmissions()->first();
        $this->assertTrue($submission->ok);
        $this->assertSame(2, $submission->url_count);
        $this->assertSame('manual', $submission->source);
    }

    public function test_sitemap_submission_uses_active_sitemap_urls(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $domain->sitemapUrls()->create(['url' => 'https://example.com/live', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $domain->sitemapUrls()->create(['url' => 'https://example.com/gone', 'first_seen_at' => now(), 'last_seen_at' => now(), 'removed_at' => now()]);
        $this->fakeHttp([new Response(202)]);

        $this->actingAs($owner)->post(route('domains.indexnow.submit', $domain), ['source' => 'sitemap']);

        $sent = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame(['https://example.com/live'], $sent['urlList']);
        $this->assertTrue($domain->indexNowSubmissions()->first()->ok);
    }

    public function test_forbidden_response_is_recorded_as_failure(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $this->fakeHttp([new Response(403)]);

        $this->actingAs($owner)->post(route('domains.indexnow.submit', $domain), ['source' => 'manual', 'urls' => 'https://example.com/a']);

        $submission = $domain->indexNowSubmissions()->first();
        $this->assertFalse($submission->ok);
        $this->assertSame(403, $submission->status_code);
    }

    public function test_no_request_is_sent_without_valid_urls(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();
        $this->fakeHttp([]);

        $this->actingAs($owner)->post(route('domains.indexnow.submit', $domain), ['source' => 'manual', 'urls' => 'https://other.com/a']);

        $this->assertCount(0, $this->history);
        $this->assertFalse($domain->indexNowSubmissions()->first()->ok);
    }

    public function test_other_users_cannot_use_indexnow_for_a_domain(): void
    {
        [, $domain] = $this->ownerWithDomain();
        $other = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($other)->get(route('domains.indexnow.key-file', $domain))->assertForbidden();
        $this->actingAs($other)->post(route('domains.indexnow.submit', $domain), ['source' => 'sitemap'])->assertForbidden();
    }

    public function test_domain_page_shows_indexnow_tab(): void
    {
        [$owner, $domain] = $this->ownerWithDomain();

        $this->actingAs($owner)->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee('tab-indexnow', false)
            ->assertSee(__('Anahtar oluştur'));
    }
}
