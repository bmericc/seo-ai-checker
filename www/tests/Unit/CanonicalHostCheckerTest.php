<?php

namespace Tests\Unit;

use App\Services\CanonicalHost\CanonicalHostChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CanonicalHostCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses): CanonicalHostChecker
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new CanonicalHostChecker($client);
    }

    public function test_permanent_redirect_to_a_different_host_is_canonical(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(301, ['Location' => 'https://www.example.com/']),
        ]);

        $result = $checker->check('example.com');

        $this->assertTrue($result->redirected);
        $this->assertSame('www.example.com', $result->canonicalHost);
        $this->assertSame(301, $result->redirectStatus);
    }

    public function test_308_redirect_is_also_treated_as_permanent(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(308, ['Location' => 'https://www.example.com/']),
        ]);

        $result = $checker->check('example.com');

        $this->assertTrue($result->redirected);
        $this->assertSame('www.example.com', $result->canonicalHost);
    }

    public function test_temporary_redirect_does_not_change_canonical_host(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(302, ['Location' => 'https://www.example.com/']),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->redirected);
        $this->assertSame('example.com', $result->canonicalHost);
    }

    public function test_redirect_to_the_same_host_is_not_a_canonical_change(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(301, ['Location' => 'https://example.com/home']),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->redirected);
        $this->assertSame('example.com', $result->canonicalHost);
    }

    public function test_no_redirect_keeps_the_original_host(): void
    {
        $checker = $this->checkerWithResponses([
            new Response(200, []),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->redirected);
        $this->assertSame('example.com', $result->originalHost);
        $this->assertSame('example.com', $result->canonicalHost);
    }

    public function test_unreachable_host_falls_back_to_itself(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/')),
        ]);

        $result = $checker->check('example.com');

        $this->assertFalse($result->redirected);
        $this->assertSame('example.com', $result->canonicalHost);
    }
}
