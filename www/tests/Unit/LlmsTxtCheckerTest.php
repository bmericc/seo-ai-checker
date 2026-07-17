<?php

namespace Tests\Unit;

use App\Services\Llms\LlmsTxtChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class LlmsTxtCheckerTest extends TestCase
{
    private function checkerWithBody(string $body, int $status = 200): LlmsTxtChecker
    {
        $mock = new MockHandler([new Response($status, [], $body)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new LlmsTxtChecker($client);
    }

    public function test_present_file_is_reported_with_preview(): void
    {
        $checker = $this->checkerWithBody("# Example\n> A short description.\n");

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->found);
        $this->assertStringContainsString('# Example', $result->preview);
    }

    public function test_missing_file_is_reported(): void
    {
        $checker = $this->checkerWithBody('Not Found', 404);

        $result = $checker->check('https://example.com/');

        $this->assertFalse($result->found);
        $this->assertNull($result->preview);
    }
}
