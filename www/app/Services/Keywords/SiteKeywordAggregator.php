<?php

declare(strict_types=1);

namespace App\Services\Keywords;

/**
 * Sayfa basina cikarilmis aday ifadeleri (sitemap_urls.keyword_phrases) ana
 * sayfa onerileriyle birlestirip site geneli tek bir oneri listesi uretir.
 *
 * Her sayfanin skorlari o sayfanin en yuksek skoruna bolunur (0-1): uzun bir
 * sayfa sirf cok kelime icerdigi icin listeyi domine etmesin. Sonra bir
 * ifadenin toplam normalize skoru, gectigi sayfa sayisinin log'u ile
 * carpilir - birden cok sayfada tekrar eden konu, tek sayfada gecen bir
 * ifadeden daha iyi bir site anahtar kelimesidir.
 *
 * Neredeyse her sayfada gecen ifadeler (sablon metni: footer'a kacmis
 * slogan, breadcrumb vb.) yeterli sayfa varsa elenir; ana sayfada aday olan
 * ifadeler bundan muaftir, boylece marka/ana konu kaybolmaz.
 */
final class SiteKeywordAggregator
{
    public const MAX_SUGGESTIONS = 25;

    /** Bu sayidan fazla sayfa taranmadan boilerplate elemesi yapilmaz. */
    private const BOILERPLATE_MIN_PAGES = 5;

    private const BOILERPLATE_RATIO = 0.8;

    /**
     * @param  KeywordSuggestion[]  $homepage  Ana sayfa onerileri (KeywordSuggester::suggest).
     * @param  array<string, array<int, array{phrase: string, score: float|int}>>  $pages  URL => o sayfanin aday ifadeleri.
     * @param  string[]  $exclude  Takip edilen ve reddedilmis ifadeler.
     * @return KeywordSuggestion[]
     */
    public function aggregate(array $homepage, array $pages, array $exclude = []): array
    {
        $excludeLower = array_flip(array_map(static fn (string $k): string => KeywordSuggester::lower(trim($k)), $exclude));

        /** @var array<string, array{sum: float, pages: int, best: float, url: ?string}> $stats */
        $stats = [];
        $homepagePhrases = [];

        $add = static function (string $phrase, float $normalized, ?string $url) use (&$stats): void {
            $stats[$phrase] ??= ['sum' => 0.0, 'pages' => 0, 'best' => 0.0, 'url' => null];
            $stats[$phrase]['sum'] += $normalized;
            $stats[$phrase]['pages']++;
            if ($normalized > $stats[$phrase]['best']) {
                $stats[$phrase]['best'] = $normalized;
                $stats[$phrase]['url'] = $url;
            }
        };

        $homepageMax = max([0.0, ...array_map(static fn (KeywordSuggestion $s): float => $s->score, $homepage)]);
        foreach ($homepage as $suggestion) {
            if ($homepageMax <= 0.0) {
                break;
            }
            $phrase = KeywordSuggester::lower($suggestion->phrase);
            $homepagePhrases[$phrase] = true;
            $add($phrase, $suggestion->score / $homepageMax, $suggestion->url);
        }

        $pageCount = 0;
        foreach ($pages as $url => $phrases) {
            if (! is_array($phrases) || $phrases === []) {
                continue;
            }

            $max = 0.0;
            $seen = [];
            foreach ($phrases as $item) {
                $phrase = KeywordSuggester::lower(trim((string) ($item['phrase'] ?? '')));
                $score = (float) ($item['score'] ?? 0);
                if ($phrase === '' || $score <= 0.0) {
                    continue;
                }
                $seen[$phrase] = max($seen[$phrase] ?? 0.0, $score);
                $max = max($max, $score);
            }

            if ($max <= 0.0) {
                continue;
            }

            $pageCount++;
            foreach ($seen as $phrase => $score) {
                $add($phrase, $score / $max, (string) $url);
            }
        }

        $scores = [];
        foreach ($stats as $phrase => $stat) {
            $phrase = (string) $phrase;

            if (isset($excludeLower[$phrase])) {
                continue;
            }

            if (
                $pageCount > self::BOILERPLATE_MIN_PAGES
                && ! isset($homepagePhrases[$phrase])
                && $stat['pages'] > $pageCount * self::BOILERPLATE_RATIO
            ) {
                continue;
            }

            $scores[$phrase] = $stat['sum'] * log(1 + $stat['pages'], 2);
        }

        arsort($scores);

        $result = [];
        foreach ($scores as $phrase => $score) {
            $phrase = (string) $phrase;
            $result[] = new KeywordSuggestion($phrase, $score, $stats[$phrase]['pages'], $stats[$phrase]['url']);
            if (count($result) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $result;
    }
}
