<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Bir LLM cevabinda domain'in gecip gecmedigini ve cevabin hangi diger
 * siteleri andigini bulur (Prompt Explorer'daki "kimler aniliyor?" listesi).
 */
final class LlmMentions
{
    /** Dosya uzantisi gibi gorunup domain olmayan kaliplar. */
    private const IGNORED_SUFFIXES = ['js', 'ts', 'php', 'py', 'txt', 'xml', 'json', 'html', 'htm', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'pdf', 'md', 'exe'];

    public static function mentions(string $text, string $domain): bool
    {
        $domain = self::normalizeHost($domain);

        return $domain !== '' && str_contains(mb_strtolower($text), $domain);
    }

    /**
     * Cevap metninde ve (varsa) alintilanan URL'lerde gecen host'lar, gecis
     * sayisina gore sirali.
     *
     * @param  list<string>  $citations
     * @return array<string, int> host => adet
     */
    public static function domainsIn(string $text, array $citations = []): array
    {
        $counts = [];

        preg_match_all('/\b((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24})\b/iu', $text, $matches);
        foreach ($matches[1] as $candidate) {
            $host = self::normalizeHost($candidate);
            $suffix = substr($host, (int) strrpos($host, '.') + 1);
            if ($host === '' || in_array($suffix, self::IGNORED_SUFFIXES, true)) {
                continue;
            }
            $counts[$host] = ($counts[$host] ?? 0) + 1;
        }

        foreach ($citations as $url) {
            $host = self::normalizeHost((string) parse_url($url, PHP_URL_HOST));
            if ($host !== '') {
                $counts[$host] = ($counts[$host] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    public static function normalizeHost(string $host): string
    {
        $host = mb_strtolower(trim($host));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
