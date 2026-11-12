<?php

declare(strict_types=1);

namespace App\Services\Llms;

use App\Models\Domain;
use App\Models\SitemapUrl;
use Illuminate\Support\Collection;

/**
 * Sitemap'ten kesfedilen sayfalardan (SitemapUrl) llmstxt.org bicimine
 * uygun bir llms.txt TASLAGI uretir: "# Site adi", "> kisa ozet", ardindan
 * URL'nin ilk yol parcasina gore gruplanmis "## Bolum" basliklari altinda
 * "- [Baslik](url): aciklama" satirlari. Baslik/aciklama, sayfa raporunda
 * on-page taramasi yapilmis sayfalarin title/meta description'indan
 * gelir; taranmamis sayfalarda URL'den turetilir.
 *
 * Yeni istek yapmaz - kullanici taslagi duzenleyip sitesinin kokune
 * /llms.txt olarak kendisi yukler.
 */
final class LlmsTxtGenerator
{
    public const MAX_PAGES = 300;

    /** Bir bolumde en fazla bu kadar satir; fazlasi kesilir. */
    public const MAX_PER_SECTION = 50;

    /**
     * Taslaga giren aktif (kaldirilmamis) sitemap sayfalari.
     *
     * @return Collection<int, SitemapUrl>
     */
    public function activePages(Domain $domain): Collection
    {
        return $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderBy('url')
            ->limit(self::MAX_PAGES)
            ->get(['id', 'domain_id', 'url', 'onpage_data', 'onpage_checked_at']);
    }

    /**
     * @param  Collection<int, SitemapUrl>  $pages  Aktif (kaldirilmamis) sitemap URL'leri.
     */
    public function generate(Domain $domain, Collection $pages): string
    {
        $root = rtrim($domain->rootUrl(), '/');
        $home = $pages->first(fn (SitemapUrl $page) => rtrim($page->url, '/') === $root);

        $siteName = $this->cleanTitle($home?->onpage_data['title'] ?? null) ?? $domain->domain;
        $summary = $this->oneLine($home?->onpage_data['meta_description'] ?? null);

        $lines = ['# ' . $siteName, ''];
        $lines[] = '> ' . ($summary ?? __(':site hakkında kısa bir açıklama yazın.', ['site' => $siteName]));
        $lines[] = '';

        $sections = [];
        foreach ($pages->take(self::MAX_PAGES) as $page) {
            if ($home !== null && $page->is($home)) {
                continue;
            }
            $sections[$this->sectionFor($page->url)][] = $page;
        }

        // Kok seviyesindeki sayfalar en uste, kalan bolumler buyukten kucuge.
        uksort($sections, function (string $a, string $b) use ($sections) {
            if ($a === '' || $b === '') {
                return $a === '' ? -1 : 1;
            }

            return count($sections[$b]) <=> count($sections[$a]) ?: strcmp($a, $b);
        });

        foreach ($sections as $section => $sectionPages) {
            $lines[] = '## ' . ($section === '' ? __('Ana sayfalar') : $this->humanize($section));
            $lines[] = '';
            foreach (array_slice($sectionPages, 0, self::MAX_PER_SECTION) as $page) {
                $lines[] = $this->linkLine($page);
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    private function linkLine(SitemapUrl $page): string
    {
        $title = $this->cleanTitle($page->onpage_data['title'] ?? null) ?? $this->titleFromUrl($page->url);
        $description = $this->oneLine($page->onpage_data['meta_description'] ?? null);

        $line = sprintf('- [%s](%s)', str_replace(['[', ']'], ['(', ')'], $title), $page->url);

        return $description !== null ? $line . ': ' . $description : $line;
    }

    private function sectionFor(string $url): string
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        // Tek parcali yollar (/hakkimizda) kok seviyesi sayilir.
        return count($segments) >= 2 ? mb_strtolower(urldecode($segments[0])) : '';
    }

    private function titleFromUrl(string $url): string
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));
        $last = $segments === [] ? '' : urldecode((string) end($segments));
        $last = (string) preg_replace('/\.(html?|php|aspx?)$/i', '', $last);

        return $last === '' ? $url : $this->humanize($last);
    }

    private function humanize(string $slug): string
    {
        return mb_convert_case(trim((string) preg_replace('/[-_]+/', ' ', $slug)), MB_CASE_TITLE);
    }

    /**
     * "Sayfa Basligi | Site Adi" kalibindaki site adi ekini atar.
     */
    private function cleanTitle(?string $title): ?string
    {
        $title = $this->oneLine($title);
        if ($title === null) {
            return null;
        }

        $parts = preg_split('/\s+[|–—]\s+/u', $title) ?: [$title];

        return trim($parts[0]) !== '' ? trim($parts[0]) : $title;
    }

    private function oneLine(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : $text;
    }
}
