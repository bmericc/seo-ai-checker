<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * LLM cevaplari genelde Markdown doner. Ham metni guvenli HTML'e cevirir
 * (CommonMark, ham HTML kapali, link'ler nofollow) ve domain'in gectigi
 * yerleri <mark> ile vurgular.
 */
final class LlmAnswerFormatter
{
    public static function toHtml(string $markdown, ?string $highlightDomain = null): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = preg_replace('/<a href=/', '<a target="_blank" rel="noopener nofollow" href=', $html) ?? $html;

        if ($highlightDomain !== null && $highlightDomain !== '') {
            $pattern = '/(?<![\w.-])((?:www\.)?' . preg_quote($highlightDomain, '/') . ')(?![\w-])(?![^<]*>)/iu';
            $html = preg_replace($pattern, '<mark>$1</mark>', $html) ?? $html;
        }

        return $html;
    }
}
