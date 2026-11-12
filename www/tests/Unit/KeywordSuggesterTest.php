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

    public function test_turkish_dotted_capital_i_is_lowercased_without_splitting_words(): void
    {
        $this->assertSame('için istanbul', KeywordSuggester::lower('İÇİN İstanbul'));

        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>İstanbul Web Tasarım</title></head>
            <body>
                <h1>İstanbul Web Tasarım</h1>
                <p>İçin İçin İçin İstanbul web tasarım hizmeti.</p>
            </body></html>
            HTML);

        $phrases = array_map(fn ($s) => $s->phrase, $suggester->suggest('https://example.com/'));
        foreach ($phrases as $phrase) {
            $this->assertStringNotContainsString('çin', $phrase);
            $this->assertStringNotContainsString("\u{0307}", $phrase);
        }
        $this->assertContains('istanbul web tasarım', $phrases);
    }

    public function test_single_word_phrases_are_never_suggested(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>Laravel</title><meta name="keywords" content="seo, laravel seo araci"></head>
            <body><h1>Laravel</h1><p>Laravel laravel laravel seo araci.</p></body></html>
            HTML);

        $phrases = array_map(fn ($s) => $s->phrase, $suggester->suggest('https://example.com/'));
        $this->assertNotEmpty($phrases);
        foreach ($phrases as $phrase) {
            $this->assertStringContainsString(' ', $phrase);
        }
        $this->assertContains('laravel seo araci', $phrases);
    }

    public function test_turkish_dotless_capital_i_lowercases_to_dotless_i_but_keeps_acronyms(): void
    {
        $this->assertSame('iç mimarlık', KeywordSuggester::lower('İÇ MİMARLIK', true));
        $this->assertSame('seo ai aracı', KeywordSuggester::lower('SEO AI ARACI', true));
        $this->assertSame('instant ai', KeywordSuggester::lower('INSTANT AI'));

        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html lang="tr"><head><title>İç Mimarlık</title></head>
            <body><h1>İÇ MİMARLIK HİZMETLERİ</h1><p>İç mimarlık projeleri.</p></body></html>
            HTML);

        $phrases = array_map(fn ($s) => $s->phrase, $suggester->suggest('https://example.com/'));
        $this->assertContains('iç mimarlık hizmetleri', $phrases);
        $this->assertNotContains('iç mimarlik', $phrases);
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

    public function test_fragments_cut_from_the_middle_of_a_phrase_are_not_suggested(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>Açık Kaynak Dernek Yazılımı</title></head>
            <body>
                <h1>Açık Kaynak Dernek Yazılımı</h1>
                <p>Ücretsiz açık kaynak dernek yazılımı ile üyelerinizi yönetin.</p>
            </body></html>
            HTML);

        $phrases = array_map(fn ($s) => $s->phrase, $suggester->suggest('https://example.com/'));

        $this->assertContains('açık kaynak dernek yazılımı', $phrases);
        $this->assertContains('açık kaynak', $phrases);
        $this->assertContains('dernek yazılımı', $phrases);
        $this->assertNotContains('kaynak dernek', $phrases);
        $this->assertNotContains('açık kaynak dernek', $phrases);
        $this->assertNotContains('kaynak dernek yazılımı', $phrases);
    }

    public function test_phrases_do_not_cross_punctuation_or_block_boundaries(): void
    {
        $suggester = $this->suggesterWithHtml(<<<'HTML'
            <html><head><title>Açık Kaynak | Dernek Yazılımı</title></head>
            <body><ul><li>Açık kaynak</li><li>Dernek yazılımı</li></ul></body></html>
            HTML);

        $phrases = array_map(fn ($s) => $s->phrase, $suggester->suggest('https://example.com/'));

        $this->assertContains('açık kaynak', $phrases);
        $this->assertContains('dernek yazılımı', $phrases);
        $this->assertNotContains('kaynak dernek', $phrases);
        $this->assertNotContains('kaynakdernek', $phrases);
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

    public function test_strip_chrome_ignores_navigation_header_and_footer_text(): void
    {
        $suggester = new KeywordSuggester(new Client());
        $html = <<<'HTML'
            <html><head><title>Dernek Muhasebe Yazilimi</title></head>
            <body>
                <header><nav><a href="/">Kurumsal Iletisim Bilgileri</a></nav></header>
                <h1>Dernek Muhasebe Yazilimi</h1>
                <footer>Kurumsal Iletisim Bilgileri</footer>
            </body></html>
            HTML;

        $withChrome = array_map(fn ($s) => $s->phrase, $suggester->suggestFromHtml($html, 'https://example.com/a'));
        $withoutChrome = array_map(fn ($s) => $s->phrase, $suggester->suggestFromHtml($html, 'https://example.com/a', stripChrome: true));

        $this->assertContains('kurumsal iletisim bilgileri', $withChrome);
        $this->assertNotContains('kurumsal iletisim bilgileri', $withoutChrome);
        $this->assertContains('dernek muhasebe yazilimi', $withoutChrome);
    }

    public function test_suggest_from_html_respects_the_limit(): void
    {
        $suggester = new KeywordSuggester(new Client());
        $html = '<html><head><title>Alfa Beta Gama Delta Epsilon Zeta Eta Teta</title></head><body></body></html>';

        $this->assertCount(3, $suggester->suggestFromHtml($html, 'https://example.com/', limit: 3));
    }
}
