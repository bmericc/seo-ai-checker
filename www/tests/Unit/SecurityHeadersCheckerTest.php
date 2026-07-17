<?php

namespace Tests\Unit;

use App\Services\Security\SecurityHeadersChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SecurityHeadersCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses): SecurityHeadersChecker
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new SecurityHeadersChecker($client);
    }

    public function test_reports_present_and_missing_headers(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, [
                'Content-Security-Policy' => "default-src 'self'",
                'X-Frame-Options' => 'DENY',
            ]),
            new Response(301, ['Location' => 'https://example.com/']),
        ]);

        $result = $checker->check('example.com');

        $this->assertTrue($result->reachable);
        $this->assertSame("default-src 'self'", $result->headers['Content-Security-Policy']);
        $this->assertSame('DENY', $result->headers['X-Frame-Options']);
        $this->assertNull($result->headers['Strict-Transport-Security']);
        $this->assertTrue($result->httpRedirectsToHttps);
    }

    public function test_http_not_redirecting_to_https_is_flagged(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, []),
            new Response(200, []),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->httpRedirectsToHttps);
    }

    public function test_redirect_to_http_does_not_count_as_https_enforced(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, []),
            new Response(301, ['Location' => 'http://example.com/other']),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->httpRedirectsToHttps);
    }

    public function test_unreachable_domain_is_reported(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/')),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->reachable);
    }
}
