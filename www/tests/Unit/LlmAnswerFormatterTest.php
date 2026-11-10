<?php

namespace Tests\Unit;

use App\Support\LlmAnswerFormatter;
use Tests\TestCase;

class LlmAnswerFormatterTest extends TestCase
{
    public function test_markdown_is_rendered_and_raw_html_escaped(): void
    {
        $html = LlmAnswerFormatter::toHtml("**Bold** <script>alert(1)</script>\n\n- item");

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<li>item</li>', $html);
    }

    public function test_domain_is_highlighted_but_not_inside_attributes(): void
    {
        $html = LlmAnswerFormatter::toHtml('See [the site](https://example.com/x) and www.example.com.', 'example.com');

        $this->assertStringContainsString('href="https://example.com/x"', $html);
        $this->assertStringContainsString('<mark>www.example.com</mark>', $html);
        $this->assertStringContainsString('rel="noopener nofollow"', $html);
        $this->assertStringNotContainsString('javascript:', LlmAnswerFormatter::toHtml('[x](javascript:alert(1))'));
    }
}
