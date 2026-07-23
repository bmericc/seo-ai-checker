<?php

namespace Tests\Unit;

use App\Services\Llm\GeminiVisibilityChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class GeminiVisibilityCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses, ?RequestInterface &$capturedRequest = null): GeminiVisibilityChecker
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
        }));
        $client = new Client(['handler' => $stack]);

        return new GeminiVisibilityChecker($client, 'gemini-3.1-flash-lite');
    }

    public function test_sends_api_key_as_query_param_and_keyword_as_content(): void
    {
        $capturedRequest = null;
        $body = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'no mention here']]]]]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body)], $capturedRequest);

        $checker->check('örnek kelime', 'example.com', 'gm-test-key');

        $this->assertStringContainsString('key=gm-test-key', $capturedRequest->getUri()->getQuery());
        $this->assertStringContainsString('gemini-3.1-flash-lite', (string) $capturedRequest->getUri());
        $sent = json_decode((string) $capturedRequest->getBody(), true);
        $this->assertSame('örnek kelime', $sent['contents'][0]['parts'][0]['text']);
    }

    public function test_domain_mentioned_in_response_is_detected(): void
    {
        $body = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'example.com iyi bir kaynaktır.']]]]]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'gm-test');

        $this->assertTrue($result->present);
    }

    public function test_domain_not_mentioned_is_reported_as_absent(): void
    {
        $body = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'other-site.com iyi bir kaynaktır.']]]]]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'gm-test');

        $this->assertFalse($result->present);
    }

    public function test_non_200_status_is_reported_as_an_error(): void
    {
        $body = json_encode(['error' => ['message' => 'API key not valid']]);
        $checker = $this->checkerWithResponses([new Response(400, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'gm-bad');

        $this->assertFalse($result->present);
        $this->assertStringContainsString('API key not valid', $result->error);
    }
}
