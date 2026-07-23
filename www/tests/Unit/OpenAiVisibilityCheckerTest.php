<?php

namespace Tests\Unit;

use App\Services\Llm\OpenAiVisibilityChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class OpenAiVisibilityCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses, ?RequestInterface &$capturedRequest = null): OpenAiVisibilityChecker
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
        }));
        $client = new Client(['handler' => $stack]);

        return new OpenAiVisibilityChecker($client, 'gpt-4.1-nano');
    }

    public function test_sends_bearer_token_and_keyword_as_user_message(): void
    {
        $capturedRequest = null;
        $body = json_encode(['choices' => [['message' => ['content' => 'no mention here']]]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body)], $capturedRequest);

        $checker->check('örnek kelime', 'example.com', 'sk-test-key');

        $this->assertSame('Bearer sk-test-key', $capturedRequest->getHeaderLine('Authorization'));
        $sent = json_decode((string) $capturedRequest->getBody(), true);
        $this->assertSame('gpt-4.1-nano', $sent['model']);
        $this->assertSame('örnek kelime', $sent['messages'][1]['content']);
    }

    public function test_domain_mentioned_in_response_is_detected_case_insensitively(): void
    {
        $body = json_encode(['choices' => [['message' => ['content' => 'Bunun için EXAMPLE.com sitesine bakabilirsin.']]]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-test');

        $this->assertTrue($result->present);
        $this->assertStringContainsString('EXAMPLE.com', $result->response);
    }

    public function test_domain_not_mentioned_is_reported_as_absent(): void
    {
        $body = json_encode(['choices' => [['message' => ['content' => 'Bunun için other-site.com sitesine bakabilirsin.']]]]);
        $checker = $this->checkerWithResponses([new Response(200, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-test');

        $this->assertFalse($result->present);
    }

    public function test_non_200_status_is_reported_as_an_error(): void
    {
        $body = json_encode(['error' => ['message' => 'Invalid API key']]);
        $checker = $this->checkerWithResponses([new Response(401, [], $body)]);

        $result = $checker->check('kelime', 'example.com', 'sk-bad');

        $this->assertFalse($result->present);
        $this->assertStringContainsString('Invalid API key', $result->error);
    }

    public function test_network_failure_is_reported_as_an_error(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('POST', self::class)),
        ]);

        $result = $checker->check('kelime', 'example.com', 'sk-test');

        $this->assertFalse($result->present);
        $this->assertNotNull($result->error);
    }
}
