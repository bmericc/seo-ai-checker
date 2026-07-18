<?php

namespace Tests\Unit;

use App\Services\Gsc\GscChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class GscCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses): GscChecker
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new GscChecker($client);
    }

    public function test_missing_access_token_skips_the_request_entirely(): void
    {
        $checker = $this->checkerWithResponses([]);

        $result = $checker->check('https://example.com/', null);

        $this->assertFalse($result->configured);
        $this->assertFalse($result->verified);
    }

    public function test_unmatched_site_is_reported_as_unverified(): void
    {
        $sitesBody = json_encode(['siteEntry' => [
            ['siteUrl' => 'sc-domain:other.com', 'permissionLevel' => 'siteOwner'],
        ]]);

        $checker = $this->checkerWithResponses([new Response(200, [], $sitesBody)]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->verified);
    }

    public function test_matched_domain_property_returns_search_analytics_totals(): void
    {
        $sitesBody = json_encode(['siteEntry' => [
            ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'],
        ]]);
        $analyticsBody = json_encode(['rows' => [
            ['clicks' => 120.0, 'impressions' => 3400.0, 'ctr' => 0.035, 'position' => 8.4],
        ]]);

        $checker = $this->checkerWithResponses([
            new Response(200, [], $sitesBody),
            new Response(200, [], $analyticsBody),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->configured);
        $this->assertTrue($result->verified);
        $this->assertSame('sc-domain:example.com', $result->siteUrl);
        $this->assertSame(120.0, $result->clicks);
        $this->assertSame(3400.0, $result->impressions);
        $this->assertSame(8.4, $result->averagePosition);
    }

    public function test_matched_url_prefix_property_is_also_recognised(): void
    {
        $sitesBody = json_encode(['siteEntry' => [
            ['siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner'],
        ]]);
        $analyticsBody = json_encode(['rows' => []]);

        $checker = $this->checkerWithResponses([
            new Response(200, [], $sitesBody),
            new Response(200, [], $analyticsBody),
        ]);

        $result = $checker->check('https://example.com/', 'token');

        $this->assertTrue($result->verified);
        $this->assertSame('https://example.com/', $result->siteUrl);
        $this->assertSame(0.0, $result->clicks);
    }

    public function test_sites_list_http_error_is_reported(): void
    {
        $body = json_encode(['error' => ['message' => 'Invalid Credentials']]);

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
}
