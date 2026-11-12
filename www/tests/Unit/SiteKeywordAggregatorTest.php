<?php

namespace Tests\Unit;

use App\Services\Keywords\KeywordSuggestion;
use App\Services\Keywords\SiteKeywordAggregator;
use PHPUnit\Framework\TestCase;

class SiteKeywordAggregatorTest extends TestCase
{
    private function phrases(array $suggestions): array
    {
        return array_map(fn (KeywordSuggestion $s) => $s->phrase, $suggestions);
    }

    public function test_falls_back_to_homepage_suggestions_without_page_data(): void
    {
        $result = (new SiteKeywordAggregator())->aggregate(
            [new KeywordSuggestion('seo araci', 10.0), new KeywordSuggestion('rank takibi', 5.0)],
            [],
        );

        $this->assertSame(['seo araci', 'rank takibi'], $this->phrases($result));
        $this->assertSame(1, $result[0]->pages);
    }

    public function test_phrase_repeated_across_pages_outranks_single_page_phrase(): void
    {
        $pages = [
            'https://example.com/a' => [['phrase' => 'tek sayfa konusu', 'score' => 10], ['phrase' => 'ortak konu', 'score' => 6]],
            'https://example.com/b' => [['phrase' => 'ortak konu', 'score' => 6], ['phrase' => 'baska', 'score' => 7]],
            'https://example.com/c' => [['phrase' => 'ortak konu', 'score' => 4], ['phrase' => 'diger', 'score' => 8]],
        ];

        $result = (new SiteKeywordAggregator())->aggregate([], $pages);

        $this->assertSame('ortak konu', $result[0]->phrase);
        $this->assertSame(3, $result[0]->pages);
        $this->assertSame('https://example.com/b', $result[0]->url);
    }

    public function test_boilerplate_on_nearly_every_page_is_dropped_unless_on_homepage(): void
    {
        $pages = [];
        for ($i = 1; $i <= 10; $i++) {
            $pages["https://example.com/p{$i}"] = [
                ['phrase' => 'tum haklari saklidir', 'score' => 5],
                ['phrase' => 'marka adi', 'score' => 5],
                ['phrase' => "konu {$i}", 'score' => 4],
            ];
        }

        $result = $this->phrases((new SiteKeywordAggregator())->aggregate(
            [new KeywordSuggestion('marka adi', 10.0)],
            $pages,
        ));

        $this->assertNotContains('tum haklari saklidir', $result);
        $this->assertContains('marka adi', $result);
    }

    public function test_excluded_phrases_are_removed_case_insensitively(): void
    {
        $pages = ['https://example.com/a' => [['phrase' => 'takip edilen', 'score' => 9], ['phrase' => 'yeni aday', 'score' => 3]]];

        $result = $this->phrases((new SiteKeywordAggregator())->aggregate([], $pages, ['Takip Edilen']));

        $this->assertSame(['yeni aday'], $result);
    }

    public function test_result_is_capped(): void
    {
        $phrases = [];
        for ($i = 0; $i < 60; $i++) {
            $phrases[] = ['phrase' => "ifade {$i}", 'score' => 60 - $i];
        }

        $result = (new SiteKeywordAggregator())->aggregate([], ['https://example.com/a' => $phrases]);

        $this->assertCount(SiteKeywordAggregator::MAX_SUGGESTIONS, $result);
    }
}
