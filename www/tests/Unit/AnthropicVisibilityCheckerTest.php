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
    private function checkerWithResponses(array $responses, ?RequestInterface &$capturedRequest = null, string $model = 'claude-haiku-5-5'): AnthropicVisibilityChecker
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
        }));
        $client = new Client(['handler' => $stack]);

        return new AnthropicVisibilityChecker($client, $model);
    }

    public function test_sends_x_api_key_header_and_keyword_as_user_message(): void
    {
        $capturedRequest = null;
        $body = json_encode(['content' => [['type' => 'text', 'text' => 'no mention here']]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body), new Response(200, [], $body)], $capturedRequest);

        $result = $checker->check('örnek kelime', 'example.com', 'sk-ant-test');

        $this->assertSame('sk-ant-test', $capturedRequest->getHeaderLine('x-api-key'));
        $this->assertSame('2023-06-01', $capturedRequest->getHeaderLine('anthropic-version'));
        $sent = json_decode((string) $capturedRequest->getBody(), true);
        $this->assertSame('claude-haiku-5-5', $sent['model']);
        $this->assertSame('örnek kelime konusunda hangi web sitelerine bakmalıyım?', $sent['messages'][0]['content']);
        $this->assertSame(1500, $sent['max_tokens']);
        $this->assertSame(['type' => 'disabled'], $sent['thinking']);
        $this->assertSame('claude-haiku-5-5', $result->model);
    }

    public function test_domain_mentioned_in_response_is_detected(): void
    {
        $body = json_encode(['content' => [['type' => 'text', 'text' => 'example.com adresini ziyaret edebilirsin.']]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body), new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-ant-test');

        $this->assertTrue($result->present);
        $this->assertSame('claude-haiku-5-5', $result->model);
    }

    public function test_extracts_text_when_thinking_block_is_present(): void
    {
        $body = json_encode([
            'content' => [
                ['type' => 'thinking', 'thinking' => 'internal thoughts'],
                ['type' => 'text', 'text' => 'example.com adresine bakabilirsin.'],
            ],
        ]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body), new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-ant-test');

        $this->assertTrue($result->present);
        $this->assertStringContainsString('example.com', $result->response);
    }

    public function test_domain_not_mentioned_is_reported_as_absent(): void
    {
        $body = json_encode(['content' => [['type' => 'text', 'text' => 'other-site.com adresini ziyaret edebilirsin.']]]);
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
