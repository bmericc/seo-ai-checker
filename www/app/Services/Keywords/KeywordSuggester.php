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

    private const NGRAM_SIZES = [1, 2, 3, 4];

    /**
     * Coklu kelimeden olusan ifadeler genelde tek basina daha genel bir
     * kelimeden daha iyi bir "anahtar kelime" adayidir (ornegin "laravel
     * seo araci", tek basina "laravel"dan daha spesifiktir); esit ham
     * frekansta uzun ifadeler one cikarilsin diye hafif bir carpan
     * uygulanir.
     */
    private const NGRAM_WEIGHT_MULTIPLIER = [1 => 1.0, 2 => 1.4, 3 => 1.6, 4 => 1.7];

    /**
     * Bir ifadenin agirlikli tekrarlarinin bu orandan fazlasi iki yandan da
     * (stopword olmayan) kelimelerle sarili ise ifade daha uzun bir ifadenin
     * ortasindan kesilmis bir parcadir: "acik kaynak dernek yazilimi"
     * icinden "kaynak dernek" gibi. Bunlar anlamsiz oneriler uretir.
     */
    private const INTERIOR_RATIO_LIMIT = 0.5;

    /**
     * Metin bu karakterlerde parcalara bolunur; n-gram'lar parca sinirini
     * asmaz ("Acik Kaynak | Dernek" -> "kaynak dernek" uretilmez).
     */
    private const SEGMENT_SEPARATORS = '/[.,;:!?|()\[\]{}"“”«»•·…–—\/\x5C]+|\s-\s|\R/u';

    /**
     * Govde metni okunurken bu elementlerin sinirlarina satir sonu konur;
     * aksi halde textContent "<li>Acik kaynak</li><li>Dernek</li>" metnini
     * "Acik kaynakDernek" diye birlestirir.
     */
    private const BLOCK_ELEMENTS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'button', 'caption',
        'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer',
        'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'label',
        'legend', 'li', 'main', 'nav', 'ol', 'option', 'p', 'section', 'select',
        'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
        'a', 'img', 'input', 'textarea',
    ];

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

        return $this->suggestFromHtml((string) $response->getBody(), $url, $exclude);
    }

    /**
     * Zaten cekilmis bir HTML'den oneri cikarir (bkz. CrawlSiteKeywordCandidates,
     * RunSitemapUrlOnPageCheck - ayni sayfa icin ikinci HTTP istegi atilmaz).
     *
     * $stripChrome, site geneli taramada nav/header/footer/aside'i da
     * atlar: bunlar her sayfada ayni tekrarlanir ve birlestirilmis listede
     * menu/footer ifadelerini ("hakkimizda", "tum haklari saklidir") one
     * cikarir. Ana sayfa onerisinde (tek sayfa) acik birakilir, cunku orada
     * menu ogeleri sitenin ana konularini yansitir.
     *
     * @param  string[]  $exclude
     * @return KeywordSuggestion[]
     */
    public function suggestFromHtml(
        string $html,
        string $url,
        array $exclude = [],
        int $limit = self::MAX_SUGGESTIONS,
        bool $stripChrome = false,
    ): array {
        if (trim($html) === '') {
            return [];
        }

        $crawler = new Crawler($html, $url);
        $this->stripNoiseElements($crawler, $stripChrome);

        $metaScores = $this->metaKeywordScores($crawler);

        /** @var array<string, array{score: float, freq: float, interior: float, n: int}> $stats */
        $stats = [];
        foreach ($this->weightedBlocks($crawler) as [$text, $weight]) {
            foreach ($this->segments($text) as $segment) {
                $this->collectPhrases($this->tokenize($segment), $weight, $stats);
            }
        }

        $scores = $this->filterFragments($stats);
        foreach ($metaScores as $phrase => $score) {
            $scores[$phrase] = ($scores[$phrase] ?? 0.0) + $score;
        }

        arsort($scores);

        $excludeLower = array_map(static fn (string $k): string => self::lower(trim($k)), $exclude);

        $suggestions = [];
        foreach ($scores as $phrase => $score) {
            if (in_array($phrase, $excludeLower, true)) {
                continue;
            }

            $suggestions[] = new KeywordSuggestion($phrase, $score);
            if (count($suggestions) >= $limit) {
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
    private function stripNoiseElements(Crawler $crawler, bool $stripChrome = false): void
    {
        $selector = 'script, style, noscript, code, pre';
        if ($stripChrome) {
            $selector .= ', nav, header, footer, aside';
        }

        foreach ($crawler->filter($selector) as $node) {
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

        foreach ($crawler->filter('h1')->each(fn (Crawler $n) => trim($this->blockText($n->getNode(0)))) as $h1) {
            if ($h1 !== '') {
                $blocks[] = [$h1, 4.0];
            }
        }

        foreach ($crawler->filter('h2')->each(fn (Crawler $n) => trim($this->blockText($n->getNode(0)))) as $h2) {
            if ($h2 !== '') {
                $blocks[] = [$h2, 2.0];
            }
        }

        $bodyText = $crawler->filter('body')->count() > 0 ? $this->blockText($crawler->filter('body')->getNode(0)) : '';
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
            $phrase = self::lower(trim($phrase));
            if ($phrase !== '') {
                $scores[$phrase] = ($scores[$phrase] ?? 0.0) + 6.0;
            }
        }

        return $scores;
    }

    /**
     * Elementin metnini blok elementlerin sinirlarina satir sonu koyarak
     * dondurur; boylece farkli bloklardaki kelimeler tek ifadede birlesmez.
     */
    private function blockText(?\DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        if ($node instanceof \DOMText) {
            return $node->nodeValue ?? '';
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->blockText($child);
        }

        if ($node instanceof \DOMElement && in_array(strtolower($node->nodeName), self::BLOCK_ELEMENTS, true)) {
            return "\n".$text."\n";
        }

        return $text;
    }

    /**
     * @return string[]
     */
    private function segments(string $text): array
    {
        $parts = preg_split(self::SEGMENT_SEPARATORS, $text) ?: [];

        return array_values(array_filter($parts, static fn (string $p): bool => trim($p) !== ''));
    }

    /**
     * Tek bir parcadaki (cumle/baslik/menu ogesi) n-gram'lari toplar. Her
     * tekrar icin, ifadenin iki yaninda da parcanin icinde stopword olmayan
     * bir kelime olup olmadigi ("ic" tekrar) ayrica sayilir.
     *
     * @param  string[]  $words
     * @param  array<string, array{score: float, freq: float, interior: float, n: int}>  $stats
     */
    private function collectPhrases(array $words, float $weight, array &$stats): void
    {
        $count = count($words);

        foreach (self::NGRAM_SIZES as $n) {
            for ($i = 0; $i <= $count - $n; $i++) {
                $slice = array_slice($words, $i, $n);

                if ($this->isStopword($slice[0]) || $this->isStopword($slice[$n - 1])) {
                    continue;
                }

                $phrase = implode(' ', $slice);
                $left = $words[$i - 1] ?? null;
                $right = $words[$i + $n] ?? null;
                $interior = $left !== null && $right !== null
                    && ! $this->isStopword($left) && ! $this->isStopword($right);

                $stats[$phrase] ??= ['score' => 0.0, 'freq' => 0.0, 'interior' => 0.0, 'n' => $n];
                $stats[$phrase]['score'] += $weight * self::NGRAM_WEIGHT_MULTIPLIER[$n];
                $stats[$phrase]['freq'] += $weight;
                if ($interior) {
                    $stats[$phrase]['interior'] += $weight;
                }
            }
        }
    }

    /**
     * Anlamsiz parcalari eler:
     *  - Cogunlukla daha uzun bir ifadenin ortasindan kesilmis ifadeler
     *    ("acik kaynak dernek yazilimi" -> "kaynak dernek").
     *  - 3+ kelimelik ifadeler, kendisini iceren bir kelime uzun ifade en az
     *    onun kadar geciyorsa (hic bagimsiz gecmiyorsa); boylece tam ifade
     *    ile bas/son ikilileri ("acik kaynak", "dernek yazilimi") kalir,
     *    "acik kaynak dernek" gibi yarim kalmis uclu elenir.
     *
     * @param  array<string, array{score: float, freq: float, interior: float, n: int}>  $stats
     * @return array<string, float>
     */
    private function filterFragments(array $stats): array
    {
        $superFreq = [];
        foreach ($stats as $phrase => $stat) {
            $words = explode(' ', (string) $phrase);
            $n = count($words);
            if ($n < 2) {
                continue;
            }
            foreach ([array_slice($words, 0, $n - 1), array_slice($words, 1)] as $sub) {
                $key = implode(' ', $sub);
                $superFreq[$key] = max($superFreq[$key] ?? 0.0, $stat['freq']);
            }
        }

        $scores = [];
        foreach ($stats as $phrase => $stat) {
            $phrase = (string) $phrase;

            if ($stat['interior'] > $stat['freq'] * self::INTERIOR_RATIO_LIMIT) {
                continue;
            }

            if ($stat['n'] >= 3 && ($superFreq[$phrase] ?? 0.0) >= $stat['freq']) {
                continue;
            }

            $scores[$phrase] = $stat['score'];
        }

        return $scores;
    }

    /**
     * Turkce buyuk "İ" mb_strtolower ile "i" + U+0307 (birlesik nokta)
     * olur; tokenize bu isareti ayirici sayip "için" kelimesini "i" ve
     * "çin" diye boluyordu. Once "İ" -> "i" yapilir, kalan birlesik
     * noktalar da temizlenir.
     */
    public static function lower(string $text): string
    {
        return str_replace("\u{0307}", '', mb_strtolower(str_replace('İ', 'i', $text)));
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = self::lower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter($words, static fn (string $w): bool => mb_strlen($w) >= 2));
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
