<?php

namespace Tests\Unit;

use App\Services\Bing\BingBacklinksChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BingBacklinksCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses, ?string $apiKey = 'test-key'): BingBacklinksChecker
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new BingBacklinksChecker($client, $apiKey);
    }

    public function test_missing_api_key_skips_the_request_entirely(): void
    {
        $checker = $this->checkerWithResponses([], apiKey: null);

        $result = $checker->check('https://example.com/');

        $this->assertFalse($result->configured);
        $this->assertFalse($result->verified);
    }

    public function test_unverified_site_is_reported(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [], json_encode(['https://other.com/'])),
        ]);

        $result = $checker->check('https://example.com/');

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

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->verified);
        $this->assertSame('https://example.com/', $result->siteUrl);
        $this->assertSame(45, $result->totalLinks);
        $this->assertSame(3, $result->pagesWithLinks);
        $this->assertSame('https://example.com/b', $result->topPages[0]['url']);
        $this->assertSame(30, $result->topPages[0]['count']);
    }

    public function test_wrapped_d_response_is_unwrapped(): void
    {
        $sitesBody = json_encode(['d' => ['https://example.com/']]);
        $linksBody = json_encode(['d' => [['Url' => 'https://example.com/a', 'Count' => 4]]]);

        $checker = $this->checkerWithResponses([
            new Response(200, [], $sitesBody),
            new Response(200, [], $linksBody),
        ]);

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->verified);
        $this->assertSame(4, $result->totalLinks);
    }

    public function test_sites_http_error_is_reported(): void
    {
        $body = json_encode(['Message' => 'Invalid ApiKey.']);

        $checker = $this->checkerWithResponses([new Response(401, [], $body)]);

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->verified);
        $this->assertStringContainsString('Invalid ApiKey', $result->error);
    }

    public function test_network_failure_is_reported_as_an_error(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', self::class)),
        ]);

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->verified);
        $this->assertNotNull($result->error);
    }
}
