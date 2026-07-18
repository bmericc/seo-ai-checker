<?php

declare(strict_types=1);

namespace App\Services\Keywords;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Bir sayfanin (genelde domain'in ana sayfasinin) HTML'ini tarayip title,
 * meta description/keywords, H1/H2 ve govde metninden agirlikli kelime/ifade
 * frekansiyla anahtar kelime aday listesi cikarir. Kullaniciya elle ekleme
 * yerine gecmez - "Anahtar Kelime Ekle" akisina ek olarak, tek tikla
 * ekleyebilecegi oneriler sunmak icindir.
 */
final class KeywordSuggester
{
    private const MAX_SUGGESTIONS = 15;

    private const NGRAM_SIZES = [1, 2, 3];

    /**
     * Coklu kelimeden olusan ifadeler genelde tek basina daha genel bir
     * kelimeden daha iyi bir "anahtar kelime" adayidir (ornegin "laravel
     * seo araci", tek basina "laravel"dan daha spesifiktir); esit ham
     * frekansta uzun ifadeler one cikarilsin diye hafif bir carpan
     * uygulanir.
     */
    private const NGRAM_WEIGHT_MULTIPLIER = [1 => 1.0, 2 => 1.4, 3 => 1.6];

    private const STOPWORDS = [
        // Turkce
        've', 'veya', 'ile', 'için', 'gibi', 'ama', 'fakat', 'ancak', 'çünkü',
        'ki', 'de', 'da', 'bu', 'şu', 'o', 'bir', 'birçok', 'her', 'hiç', 'çok',
        'az', 'en', 'daha', 'ise', 'olan', 'olarak', 'olur', 'oldu', 'mi', 'mı',
        'mu', 'mü', 'ne', 'nasıl', 'neden', 'niçin', 'kadar', 'sonra', 'önce',
        'üzere', 'göre', 'karşı', 'arasında', 'içinde', 'üzerinde', 'altında',
        'biz', 'siz', 'ben', 'sen', 'onlar', 'bizim', 'sizin', 'benim', 'senin',
        'var', 'yok', 'tüm', 'hem', 'ya', 'yani', 'işte', 'şey', 'şeyler',
        // Ingilizce
        'the', 'a', 'an', 'and', 'or', 'but', 'if', 'then', 'else', 'for', 'to',
        'of', 'in', 'on', 'at', 'by', 'with', 'from', 'as', 'is', 'are', 'was',
        'were', 'be', 'been', 'being', 'this', 'that', 'these', 'those', 'it',
        'its', 'they', 'them', 'their', 'we', 'you', 'your', 'i', 'he', 'she',
        'his', 'her', 'not', 'no', 'yes', 'do', 'does', 'did', 'have', 'has',
        'had', 'will', 'would', 'can', 'could', 'should', 'may', 'might',
        'must', 'about', 'into', 'than', 'so', 'such', 'also', 'more', 'most',
        'some', 'any', 'all', 'each', 'other', 'which', 'what', 'who', 'whom',
        'when', 'where', 'why', 'how',
    ];

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param  string[]  $exclude  Zaten takip edilen anahtar kelimeler; sonuclardan cikarilir (buyuk/kucuk harf duyarsiz).
     * @return KeywordSuggestion[]
     */
    public function suggest(string $url, array $exclude = []): array
    {
        try {
            $response = $this->client->get($url);
        } catch (GuzzleException) {
            return [];
        }

        $html = (string) $response->getBody();
        if (trim($html) === '') {
            return [];
        }

        $crawler = new Crawler($html, $url);
        $this->stripNoiseElements($crawler);

        $scores = $this->metaKeywordScores($crawler);

        foreach ($this->weightedBlocks($crawler) as [$text, $weight]) {
            foreach ($this->scorePhrases($text, $weight) as $phrase => $score) {
                $scores[$phrase] = ($scores[$phrase] ?? 0.0) + $score;
            }
        }

        arsort($scores);

        $excludeLower = array_map(static fn (string $k): string => mb_strtolower(trim($k)), $exclude);

        $suggestions = [];
        foreach ($scores as $phrase => $score) {
            if (in_array($phrase, $excludeLower, true)) {
                continue;
            }

            $suggestions[] = new KeywordSuggestion($phrase, $score);
            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * DomCrawler::text() bir elementin altindaki TUM metin dugumlerini
     * dondurur - <script>/<style> icerigi, kod ornekleri (<pre>/<code>) dahil.
     * Bunlar govde metninden anahtar kelime cikarirken salt gurultudur;
     * asil metni okumadan once DOM'dan kaldirilir.
     */
    private function stripNoiseElements(Crawler $crawler): void
    {
        foreach ($crawler->filter('script, style, noscript, code, pre') as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * @return array<int, array{0: string, 1: float}>
     */
    private function weightedBlocks(Crawler $crawler): array
    {
        $blocks = [];

        $title = $this->firstText($crawler, 'title');
        if ($title !== null) {
            $blocks[] = [$title, 5.0];
        }

        $description = $this->metaContent($crawler, 'description');
        if ($description !== null) {
            $blocks[] = [$description, 3.0];
        }

        foreach ($crawler->filter('h1')->each(static fn (Crawler $n) => trim($n->text(''))) as $h1) {
            if ($h1 !== '') {
                $blocks[] = [$h1, 4.0];
            }
        }

        foreach ($crawler->filter('h2')->each(static fn (Crawler $n) => trim($n->text(''))) as $h2) {
            if ($h2 !== '') {
                $blocks[] = [$h2, 2.0];
            }
        }

        $bodyText = $crawler->filter('body')->count() > 0 ? $crawler->filter('body')->text('') : '';
        if (trim($bodyText) !== '') {
            $blocks[] = [$bodyText, 1.0];
        }

        return $blocks;
    }

    /**
     * <meta name="keywords"> nadiren kullanilsa da varsa yazarin kendi
     * belirttigi ifadelerdir; virgulle ayrilip yuksek agirlikla eklenir.
     *
     * @return array<string, float>
     */
    private function metaKeywordScores(Crawler $crawler): array
    {
        $content = $this->metaContent($crawler, 'keywords');
        if ($content === null) {
            return [];
        }

        $scores = [];
        foreach (explode(',', $content) as $phrase) {
            $phrase = mb_strtolower(trim($phrase));
            if ($phrase !== '') {
                $scores[$phrase] = ($scores[$phrase] ?? 0.0) + 6.0;
            }
        }

        return $scores;
    }

    /**
     * @return array<string, float>
     */
    private function scorePhrases(string $text, float $weight): array
    {
        $words = $this->tokenize($text);
        $scores = [];

        foreach (self::NGRAM_SIZES as $n) {
            $ngramWeight = $weight * self::NGRAM_WEIGHT_MULTIPLIER[$n];
            foreach ($this->ngrams($words, $n) as $phrase) {
                $scores[$phrase] = ($scores[$phrase] ?? 0.0) + $ngramWeight;
            }
        }

        return $scores;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter($words, static fn (string $w): bool => mb_strlen($w) >= 2));
    }

    /**
     * @param  string[]  $words
     * @return string[]
     */
    private function ngrams(array $words, int $n): array
    {
        $grams = [];
        $count = count($words);

        for ($i = 0; $i <= $count - $n; $i++) {
            $slice = array_slice($words, $i, $n);

            if ($this->isStopword($slice[0]) || $this->isStopword($slice[$n - 1])) {
                continue;
            }

            $grams[] = implode(' ', $slice);
        }

        return $grams;
    }

    private function isStopword(string $word): bool
    {
        return in_array($word, self::STOPWORDS, true) || is_numeric($word);
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);

        return $node->count() > 0 ? trim($node->first()->text('')) : null;
    }

    private function metaContent(Crawler $crawler, string $name): ?string
    {
        $node = $crawler->filter(sprintf('meta[name="%s"]', $name));
        if ($node->count() === 0) {
            return null;
        }

        $content = $node->first()->attr('content');

        return $content !== null && trim($content) !== '' ? trim($content) : null;
    }
}
