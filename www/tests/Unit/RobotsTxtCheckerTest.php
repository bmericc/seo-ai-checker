<?php

namespace Tests\Unit;

use App\Services\Robots\RobotsTxtChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RobotsTxtCheckerTest extends TestCase
{
    private function checkerWithBody(string $body, int $status = 200): RobotsTxtChecker
    {
        $mock = new MockHandler([new Response($status, [], $body)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new RobotsTxtChecker($client);
    }

    public function test_specific_agent_disallow_blocks_that_crawler_only(): void
    {
        $checker = $this->checkerWithBody(<<<'ROBOTS'
            User-agent: GPTBot
            Disallow: /

            User-agent: *
            Allow: /
            ROBOTS);

        $result = $checker->check('https://example.com/page');

        $this->assertTrue($result->found);
        $this->assertFalse($result->crawlers['GPTBot']['allowed']);
        $this->assertTrue($result->crawlers['ClaudeBot']['allowed']);
    }

    public function test_wildcard_disallow_blocks_unlisted_crawlers(): void
    {
        $checker = $this->checkerWithBody(<<<'ROBOTS'
            User-agent: *
            Disallow: /
            ROBOTS);

        $result = $checker->check('https://example.com/page');

        $this->assertFalse($result->crawlers['GPTBot']['allowed']);
        $this->assertFalse($result->crawlers['ClaudeBot']['allowed']);
    }

    public function test_path_specific_disallow_only_blocks_matching_path(): void
    {
        $robots = <<<'ROBOTS'
            User-agent: GPTBot
            Disallow: /private/
            ROBOTS;

        $blocked = $this->checkerWithBody($robots)->check('https://example.com/private/secret');
        $allowed = $this->checkerWithBody($robots)->check('https://example.com/public/page');

        $this->assertFalse($blocked->crawlers['GPTBot']['allowed']);
        $this->assertTrue($allowed->crawlers['GPTBot']['allowed']);
    }

    public function test_more_specific_allow_overrides_broader_disallow(): void
    {
        $checker = $this->checkerWithBody(<<<'ROBOTS'
            User-agent: GPTBot
            Disallow: /
            Allow: /public/
            ROBOTS);

        $result = $checker->check('https://example.com/public/page');

        $this->assertTrue($result->crawlers['GPTBot']['allowed']);
    }

    public function test_missing_robots_txt_defaults_to_allowed_for_all(): void
    {
        $checker = $this->checkerWithBody('', 404);

        $result = $checker->check('https://example.com/page');

        $this->assertFalse($result->found);
        $this->assertSame([], $result->crawlers);
    }

    public function test_grouped_user_agents_share_the_same_rules(): void
    {
        $checker = $this->checkerWithBody(<<<'ROBOTS'
            User-agent: GPTBot
            User-agent: ClaudeBot
            Disallow: /
            ROBOTS);

        $result = $checker->check('https://example.com/page');

        $this->assertFalse($result->crawlers['GPTBot']['allowed']);
        $this->assertFalse($result->crawlers['ClaudeBot']['allowed']);
        $this->assertTrue($result->crawlers['PerplexityBot']['allowed']);
    }
}
