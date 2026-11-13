<?php

namespace Tests\Unit;

use App\Services\Bing\BingBacklinksChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BingBacklinksCheckerTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $requests = [];

    /**
     * Verilen yanitlarin ardina bos GetUrlLinks yanitlari eklenir; boylece
     * link verenleri test etmeyen senaryolar her sayfa icin yanit yazmak
     * zorunda kalmaz.
     */
    private function checkerWithResponses(array $responses): BingBacklinksChecker
    {
        for ($i = 0; $i < BingBacklinksChecker::LINKING_PAGES_LIMIT; $i++) {
            $responses[] = new Response(200, [], json_encode(['d' => ['Details' => [], 'TotalPages' => 0]]));
        }

        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));

        return new BingBacklinksChecker(new Client(['handler' => $stack]));
    }

    public function test_missing_access_token_skips_the_request_entirely(): void
    {
        $checker = $this->checkerWithResponses([]);

        $result = $checker->check('https://example.com/', null);

        $this->assertFalse($result->configured);
        $this->assertFalse($result->verified);
    }

    public function test_unverified_site_is_reported(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [], json_encode(['https://other.com/'])),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->verified);
    }

    public function test_verified_site_returns_link_totals_and_top_pages(): void
    {
        $sitesBody = json_encode(['https://example.com/']);
        $linksBody = json_encode([
            ['Url' => 'https://example.com/a', 'Count' => 10],
            ['Url' => 'https://example.com/b', 'Count' => 30],
            ['Url' => 'https://example.com/c', 'Count' => 5],
        ]);

        $checker = $this->checkerWithResponses([
            new Response(200, [], $sitesBody),
            new Response(200, [], $linksBody),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->verified);
        $this->assertSame('https://example.com/', $result->siteUrl);
        $this->assertSame(45, $result->totalLinks);
        $this->assertSame(3, $result->pagesWithLinks);
        $this->assertSame('https://example.com/b', $result->topPages[0]['url']);
        $this->assertSame(30, $result->topPages[0]['count']);
    }

    /**
     * Bing bir siteyi "www." ile ya da onsuz dogrulayabiliyor -
     * GetUserSites'in gercek ciktisinda bunlar birbirinden bagimsiz karisik
     * halde donuyor (bkz. BingBacklinksChecker::matchSite). Domain'imiz
     * "www"siz saklansa da Bing'de "www." ile dogrulanmis olabilir.
     */
    public function test_site_verified_with_a_www_prefix_still_matches_a_bare_domain(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [], json_encode(['http://www.example.com/'])),
            new Response(200, [], json_encode([['Url' => 'http://www.example.com/a', 'Count' => 7]])),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->verified);
        $this->assertSame('http://www.example.com/', $result->siteUrl);
        $this->assertSame(7, $result->totalLinks);
    }

    /**
     * Simetrik durum: domain'imiz "www." ile saklansa da Bing'de "www"siz
     * dogrulanmis olabilir.
     */
    public function test_bare_domain_verified_in_bing_still_matches_a_www_prefixed_root_url(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [], json_encode(['https://example.com/'])),
            new Response(200, [], json_encode([['Url' => 'https://example.com/a', 'Count' => 3]])),
        ]);

        $result = $checker->check('https://www.example.com/', 'token');

        $this->assertTrue($result->verified);
        $this->assertSame('https://example.com/', $result->siteUrl);
    }

    public function test_wrapped_d_response_is_unwrapped(): void
    {
        $sitesBody = json_encode(['d' => ['https://example.com/']]);
        $linksBody = json_encode(['d' => [['Url' => 'https://example.com/a', 'Count' => 4]]]);

        $checker = $this->checkerWithResponses([
            new Response(200, [], $sitesBody),
            new Response(200, [], $linksBody),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->verified);
        $this->assertSame(4, $result->totalLinks);
    }

    public function test_sites_http_error_is_reported(): void
    {
        $body = json_encode(['Message' => 'Invalid Credentials.']);

        $checker = $this->checkerWithResponses([new Response(401, [], $body)]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->verified);
        $this->assertStringContainsString('Invalid Credentials', $result->error);
    }

    public function test_network_failure_is_reported_as_an_error(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', self::class)),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->verified);
        $this->assertNotNull($result->error);
    }

    public function test_documented_links_wrapper_is_unwrapped(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [], json_encode(['d' => [['Url' => 'https://example.com/', 'Verified' => true]]])),
            new Response(200, [], json_encode(['d' => ['Links' => [['Url' => 'https://example.com/a', 'Count' => 14]], 'TotalPages' => 1]])),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertSame(14, $result->totalLinks);
        $this->assertSame('https://example.com/a', $result->topPages[0]['url']);
    }

    public function test_linking_urls_of_top_pages_are_grouped_by_host(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [], json_encode(['https://example.com/'])),
            new Response(200, [], json_encode(['d' => ['Links' => [
                ['Url' => 'https://example.com/a', 'Count' => 10],
                ['Url' => 'https://example.com/b', 'Count' => 30],
            ], 'TotalPages' => 1]])),
            // En cok linklenen /b once sorgulanir.
            new Response(200, [], json_encode(['d' => ['Details' => [
                ['Url' => 'https://www.blog.net/yazi-1', 'AnchorText' => 'ornek site'],
                ['Url' => 'https://blog.net/yazi-2', 'AnchorText' => ''],
                ['Url' => 'https://example.com/ic-link', 'AnchorText' => 'kendi'],
                ['Url' => 'https://shop.example.com/x', 'AnchorText' => 'alt alan'],
                ['Url' => 'javascript://evil.com/%0Aalert(1)', 'AnchorText' => 'x'],
            ], 'TotalPages' => 1]])),
            new Response(500, [], 'boom'),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertSame([
            ['domain' => 'blog.net', 'links' => 2, 'anchor' => 'ornek site', 'url' => 'https://www.blog.net/yazi-1', 'target' => 'https://example.com/b'],
        ], $result->referringDomains);

        $query = [];
        parse_str($this->requests[2]['request']->getUri()->getQuery(), $query);
        $this->assertStringEndsWith('/GetUrlLinks', $this->requests[2]['request']->getUri()->getPath());
        $this->assertSame(['siteUrl' => 'https://example.com/', 'link' => 'https://example.com/b', 'page' => '0'], $query);
        $this->assertCount(4, $this->requests);
    }
}
