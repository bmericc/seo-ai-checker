<?php

namespace Tests\Unit;

use App\Services\Llms\LlmsTxtValidator;
use Tests\TestCase;

class LlmsTxtValidatorTest extends TestCase
{
    private function codes(array $result): array
    {
        return array_column($result['issues'], 'code');
    }

    public function test_well_formed_file_is_valid_without_issues(): void
    {
        $body = "# Örnek\n\n> Koşu ayakkabıları satan mağaza.\n\n## Blog\n\n- [Rehber](https://www.example.com/blog/rehber): Başlangıç\n- [İade](/iade)\n";

        $result = (new LlmsTxtValidator())->validate($body, 'text/plain; charset=utf-8', 'example.com');

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['issues']);
        $this->assertSame('Örnek', $result['title']);
        $this->assertSame(1, $result['section_count']);
        $this->assertSame(2, $result['link_count']);
    }

    public function test_html_response_is_an_error(): void
    {
        $result = (new LlmsTxtValidator())->validate("<!DOCTYPE html><html><body>App</body></html>", 'text/html', 'example.com');

        $this->assertFalse($result['valid']);
        $this->assertSame(['html'], $this->codes($result));
    }

    public function test_missing_title_summary_and_malformed_links_are_reported(): void
    {
        $body = "Hoş geldiniz\n\n- [Kırık link](\n- [Dış](https://other.com/a)\n- [Dış 2](https://other.org/b)\n";

        $result = (new LlmsTxtValidator())->validate($body, 'text/plain', 'example.com');

        $this->assertFalse($result['valid']);
        $this->assertSame(['missing_title', 'no_sections', 'malformed_links', 'mostly_external'], $this->codes($result));
    }

    public function test_title_without_summary_or_links_is_valid_with_warnings(): void
    {
        $result = (new LlmsTxtValidator())->validate("# Örnek\nSadece metin.\n", null, 'example.com');

        $this->assertTrue($result['valid']);
        $this->assertSame(['missing_summary', 'no_links'], $this->codes($result));
    }
}
