<?php

namespace Tests\Unit;

use App\Services\Llm\AnthropicVisibilityChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class AnthropicVisibilityCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses, ?RequestInterface &$capturedRequest = null): AnthropicVisibilityChecker
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
        }));
        $client = new Client(['handler' => $stack]);

        return new AnthropicVisibilityChecker($client, 'claude-haiku-4-5-20251001');
    }

    public function test_sends_x_api_key_header_and_keyword_as_user_message(): void
    {
        $capturedRequest = null;
        $body = json_encode(['content' => [['text' => 'no mention here']]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body), new Response(200, [], $body)], $capturedRequest);

        $checker->check('örnek kelime', 'example.com', 'sk-ant-test');

        $this->assertSame('sk-ant-test', $capturedRequest->getHeaderLine('x-api-key'));
        $this->assertSame('2023-06-01', $capturedRequest->getHeaderLine('anthropic-version'));
        $sent = json_decode((string) $capturedRequest->getBody(), true);
        $this->assertSame('claude-haiku-4-5-20251001', $sent['model']);
        $this->assertSame('örnek kelime konusunda hangi web sitelerine bakmalıyım?', $sent['messages'][0]['content']);
    }

    public function test_domain_mentioned_in_response_is_detected(): void
    {
        $body = json_encode(['content' => [['text' => 'example.com adresini ziyaret edebilirsin.']]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body), new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-ant-test');

        $this->assertTrue($result->present);
    }

    public function test_domain_not_mentioned_is_reported_as_absent(): void
    {
        $body = json_encode(['content' => [['text' => 'other-site.com adresini ziyaret edebilirsin.']]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body), new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-ant-test');

        $this->assertFalse($result->present);
    }

    public function test_non_200_status_is_reported_as_an_error(): void
    {
        $body = json_encode(['error' => ['message' => 'authentication_error']]);
        $checker = $this->checkerWithResponses([new Response(401, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-bad');

        $this->assertFalse($result->present);
        $this->assertStringContainsString('authentication_error', $result->error);
    }
}
