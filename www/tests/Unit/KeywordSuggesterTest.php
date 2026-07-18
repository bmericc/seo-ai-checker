<?php

namespace Tests\Unit;

use App\Services\Keywords\KeywordSuggester;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class KeywordSuggesterTest extends TestCase
{
    private function suggesterWithHtml(string $html): KeywordSuggester
    {
        $mock = new MockHandler([new Response(200, [], $html)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new KeywordSuggester($client);
    }

    public function test_title_and_h1_phrases_rank_above_generic_body_text(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>Laravel SEO Araci</title></head>
            <body>
                <h1>Laravel SEO Araci</h1>
                <p>Bu sayfa sadece rastgele metinler icerir ve tekrar etmez.</p>
            </body></html>
            HTML);

        $suggestions = $suggester->suggest('https://example.com/');

        $phrases = array_map(fn ($s) => $s->phrase, $suggestions);
        $this->assertContains('laravel seo', $phrases);
        $this->assertStringContainsString(' ', $suggestions[0]->phrase, 'top suggestion should be a multi-word phrase, not a bare generic word');
    }

    public function test_meta_keywords_are_included_with_high_weight(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head>
                <title>Ornek Site</title>
                <meta name="keywords" content="ozel ifade, baska terim">
            </head><body><p>Genel govde metni.</p></body></html>
            HTML);

        $suggestions = $suggester->suggest('https://example.com/');

        $phrases = array_map(fn ($s) => $s->phrase, $suggestions);
        $this->assertContains('ozel ifade', $phrases);
        $this->assertContains('baska terim', $phrases);
    }

    public function test_already_tracked_keywords_are_excluded(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>Laravel SEO Araci</title></head>
            <body><h1>Laravel SEO Araci</h1></body></html>
            HTML);

        $suggestions = $suggester->suggest('https://example.com/', exclude: ['Laravel SEO']);

        $phrases = array_map(fn ($s) => $s->phrase, $suggestions);
        $this->assertNotContains('laravel seo', $phrases);
    }

    public function test_stopword_only_phrases_are_not_suggested(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>ve ile de</title></head>
            <body><p>ve ile de bu şu o</p></body></html>
            HTML);

        $suggestions = $suggester->suggest('https://example.com/');

        $this->assertSame([], $suggestions);
    }

    public function test_unreachable_page_returns_no_suggestions(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'https://example.com/')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $suggester = new KeywordSuggester($client);

        $suggestions = $suggester->suggest('https://example.com/');

        $this->assertSame([], $suggestions);
    }
}
