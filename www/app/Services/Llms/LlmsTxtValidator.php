<?php

declare(strict_types=1);

namespace App\Services\Llms;

/**
 * Bulunan bir /llms.txt dosyasinin llmstxt.org bicimine uyup uymadigini
 * denetler (yeni istek yapmaz, LlmsTxtChecker'in cektigi govdeyi okur):
 *  - error: dosya aslinda HTML (SPA'lar bilinmeyen her yola index.html
 *    dondurur - "var" gorunur ama gercek bir llms.txt degildir), bos ya
 *    da "# Baslik" ile baslamiyor
 *  - warning: "> ozet" satiri yok, hic "- [baslik](url)" linki yok,
 *    bicimi bozuk liste satirlari, baska sitelere giden linkler cok
 * Sonuc yalnizca bilgi amaclidir; saglik skoruna agirlik olarak girmez
 * (bkz. LlmsTxtChecker sinif yorumu).
 */
final class LlmsTxtValidator
{
    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARNING = 'warning';

    /** Raporda en fazla bu kadar bozuk satir ornegi gosterilir. */
    private const MAX_EXAMPLES = 3;

    private const LINK_PATTERN = '/^\s*[-*]\s+\[([^\]]+)\]\(([^)\s]+)\)(?::\s*(.*))?\s*$/u';

    /**
     * @return array{valid: bool, title: ?string, has_summary: bool, section_count: int, link_count: int, external_link_count: int, issues: list<array{level: string, code: string, message: string}>}
     */
    public function validate(string $body, ?string $contentType, string $siteHost): array
    {
        $issues = [];
        $title = null;
        $hasSummary = false;
        $sections = 0;
        $links = 0;
        $external = 0;
        $malformed = [];

        $trimmed = ltrim($body, "\xEF\xBB\xBF \t\r\n");

        if ($this->looksLikeHtml($trimmed, $contentType)) {
            $issues[] = $this->issue(self::LEVEL_ERROR, 'html', __('Dosya düz metin değil HTML döndürüyor. Sunucu /llms.txt için büyük ihtimalle site sayfasını veriyor; gerçek bir llms.txt dosyası yükleyin.'));

            return $this->result(false, null, false, 0, 0, 0, $issues);
        }

        if ($trimmed === '') {
            $issues[] = $this->issue(self::LEVEL_ERROR, 'empty', __('Dosya boş.'));

            return $this->result(false, null, false, 0, 0, 0, $issues);
        }

        $lines = preg_split('/\R/u', $trimmed) ?: [];
        $firstContent = null;
        $afterTitle = false;
        $site = $this->normalizeHost($siteHost);

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                continue;
            }

            if ($firstContent === null) {
                $firstContent = $line;
                if (preg_match('/^#\s+(.+)$/u', $line, $m) === 1) {
                    $title = trim($m[1]);
                    $afterTitle = true;
                    continue;
                }
            }

            if ($afterTitle) {
                $afterTitle = false;
                if (str_starts_with(ltrim($line), '>')) {
                    $hasSummary = true;
                    continue;
                }
            }

            if (preg_match('/^##\s+\S/u', $line) === 1) {
                $sections++;
                continue;
            }

            if (preg_match('/^\s*[-*]\s+/u', $line) === 1) {
                if (preg_match(self::LINK_PATTERN, $line, $m) === 1) {
                    $links++;
                    $host = $this->normalizeHost((string) parse_url($m[2], PHP_URL_HOST));
                    if ($host !== '' && $site !== '' && $host !== $site && !str_ends_with($host, '.' . $site)) {
                        $external++;
                    }
                } elseif (str_contains($line, '](') || str_contains($line, '[')) {
                    $malformed[] = trim($line);
                }
            }
        }

        if ($title === null) {
            $issues[] = $this->issue(self::LEVEL_ERROR, 'missing_title', __('Dosya "# Site adı" biçiminde bir H1 başlıkla başlamıyor.'));
        }
        if ($title !== null && !$hasSummary) {
            $issues[] = $this->issue(self::LEVEL_WARNING, 'missing_summary', __('Başlığın hemen altında "> kısa özet" satırı yok. Asistanlar sitenizi ilk bu satırdan tanır.'));
        }
        if ($links === 0) {
            $issues[] = $this->issue(self::LEVEL_WARNING, 'no_links', __('Hiç "- [Başlık](URL)" biçiminde sayfa linki yok.'));
        } elseif ($sections === 0) {
            $issues[] = $this->issue(self::LEVEL_WARNING, 'no_sections', __('Linkler "## Bölüm" başlıkları altında gruplanmamış.'));
        }
        if ($malformed !== []) {
            $issues[] = $this->issue(self::LEVEL_WARNING, 'malformed_links', __(':count liste satırı link biçimine uymuyor, örnek: :example', [
                'count' => count($malformed),
                'example' => implode(' · ', array_map(fn ($l) => mb_strimwidth($l, 0, 80, '…'), array_slice($malformed, 0, self::MAX_EXAMPLES))),
            ]));
        }
        if ($links > 0 && $external * 2 > $links) {
            $issues[] = $this->issue(self::LEVEL_WARNING, 'mostly_external', __('Linklerin çoğu (:count / :total) başka sitelere gidiyor. llms.txt kendi önemli sayfalarınızı listelemeli.', ['count' => $external, 'total' => $links]));
        }

        $valid = collect($issues)->where('level', self::LEVEL_ERROR)->isEmpty();

        return $this->result($valid, $title, $hasSummary, $sections, $links, $external, $issues);
    }

    private function looksLikeHtml(string $body, ?string $contentType): bool
    {
        if ($contentType !== null && str_contains(mb_strtolower($contentType), 'text/html')) {
            return true;
        }

        return preg_match('/^(<!doctype\s+html|<html[\s>]|<head[\s>]|<body[\s>])/i', $body) === 1;
    }

    private function normalizeHost(string $host): string
    {
        $host = mb_strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** @return array{level: string, code: string, message: string} */
    private function issue(string $level, string $code, string $message): array
    {
        return compact('level', 'code', 'message');
    }

    private function result(bool $valid, ?string $title, bool $hasSummary, int $sections, int $links, int $external, array $issues): array
    {
        return [
            'valid' => $valid,
            'title' => $title,
            'has_summary' => $hasSummary,
            'section_count' => $sections,
            'link_count' => $links,
            'external_link_count' => $external,
            'issues' => $issues,
        ];
    }
}
