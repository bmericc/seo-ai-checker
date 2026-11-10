<?php

namespace Tests\Unit;

use App\Services\Llm\LlmMentions;
use PHPUnit\Framework\TestCase;

class LlmMentionsTest extends TestCase
{
    public function test_mentions_ignores_case_and_www(): void
    {
        $this->assertTrue(LlmMentions::mentions('Visit Example.COM today', 'www.example.com'));
        $this->assertFalse(LlmMentions::mentions('Visit other.com', 'example.com'));
    }

    public function test_domains_in_text_and_citations_are_counted(): void
    {
        $domains = LlmMentions::domainsIn('Try www.rival.com, rival.com or shop.example.org. See config.php and app.js.', ['https://rival.com/x', 'https://news.site.net/a']);

        $this->assertSame(3, $domains['rival.com']);
        $this->assertSame(1, $domains['shop.example.org']);
        $this->assertSame(1, $domains['news.site.net']);
        $this->assertArrayNotHasKey('config.php', $domains);
        $this->assertArrayNotHasKey('app.js', $domains);
    }
}
