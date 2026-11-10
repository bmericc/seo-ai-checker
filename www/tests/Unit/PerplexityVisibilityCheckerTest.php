<?php

namespace Tests\Unit;

use App\Services\Llm\PerplexityVisibilityChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PerplexityVisibilityCheckerTest extends TestCase
{
    private function checker(array $responses, array &$history = []): PerplexityVisibilityChecker
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new PerplexityVisibilityChecker(new Client(['handler' => $stack]), 'sonar');
    }

    public function test_check_sends_bearer_key_and_detects_domain_in_citations(): void
    {
        $history = [];
        $first = json_encode(['choices' => [['message' => ['content' => 'Some sites [1]']]], 'search_results' => [['url' => 'https://blog.example.com/post']]]);
        $second = json_encode(['choices' => [['message' => ['content' => 'Turkish ones: none']]]]);
        $checker = $this->checker([new Response(200, [], $first), new Response(200, [], $second)], $history);

        $result = $checker->check('kelime', 'example.com', 'pplx-key');

        $this->assertSame('Bearer pplx-key', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('https://api.perplexity.ai/chat/completions', (string) $history[0]['request']->getUri());
        $this->assertSame('sonar', json_decode((string) $history[0]['request']->getBody(), true)['model']);
        $this->assertTrue($result->present);
        $this->assertFalse($result->followUpPresent);
        $this->assertSame(['https://blog.example.com/post'], $result->citations);
        $this->assertSame(['https://blog.example.com/post'], $result->toArray()['citations']);
    }

    public function test_api_errors_are_reported(): void
    {
        $checker = $this->checker([new Response(401, [], json_encode(['error' => ['message' => 'Invalid API key']]))]);

        $result = $checker->ask('q', 'example.com', 'bad');

        $this->assertFalse($result->present);
        $this->assertSame('Perplexity HTTP 401: Invalid API key', $result->error);
    }
}
