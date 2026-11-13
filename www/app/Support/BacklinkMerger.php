<?php

declare(strict_types=1);

namespace App\Support;

/**
 * DataForSEO, CrawlGraph (Common Crawl) ve Bing Webmaster referring domain
 * listelerini tek, mukerrersiz bir listede birlestirir.
 *
 * CrawlGraph kayit edilebilir domain duzeyinde (orn. "ornek.com.tr") calisir,
 * DataForSEO ise host verir (orn. "blog.ornek.com.tr"). Public suffix listesi
 * tutmamak icin eslestirme CrawlGraph tarafindaki domain'lere gore yapilir:
 * bir DataForSEO host'u, bir CrawlGraph domain'ine esitse ya da onun alt
 * alan adiysa o satira baglanir. Eslesmeyenler kendi (www'siz) host'uyla
 * ayri satir olur. Ayni anahtara dusen birden cok DataForSEO host'u tek
 * satirda toplanir. Bing host'lari da ayni kuralla (CrawlGraph domain'ine,
 * yoksa kendi www'siz host'una) baglanir.
 */
final class BacklinkMerger
{
    public const SOURCE_DATAFORSEO = 'dataforseo';

    public const SOURCE_CRAWLGRAPH = 'crawlgraph';

    public const SOURCE_BING = 'bing';

    /**
     * @param  ?list<array<string, mixed>>  $dataForSeo  BacklinksFetcher satirlari (domain, rank, backlinks, nofollow, spam_score, first_seen, lost_date)
     * @param  ?list<array<string, mixed>>  $crawlGraph  CrawlGraphClient satirlari (domain, hosts, tld, authority)
     * @param  ?list<array<string, mixed>>  $bing  BingBacklinksChecker satirlari (domain, links, anchor, url, target)
     * @return list<array{domain: string, sources: list<string>, rank: ?int, backlinks: ?int, nofollow: bool, spam_score: ?int, first_seen: ?string, lost_date: ?string, authority: ?int, hosts: ?int, bing_links: ?int, anchor: ?string, sample_url: ?string}>
     */
    public static function merge(?array $dataForSeo, ?array $crawlGraph, ?array $bing = null): array
    {
        $rows = [];

        foreach ($crawlGraph ?? [] as $cg) {
            $key = Domain::normalize((string) ($cg['domain'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[$key] = self::empty($key);
            $rows[$key]['sources'][] = self::SOURCE_CRAWLGRAPH;
            $rows[$key]['authority'] = $cg['authority'] ?? null;
            $rows[$key]['hosts'] = $cg['hosts'] ?? null;
        }
        $cgKeys = array_fill_keys(array_keys($rows), true);

        foreach ($dataForSeo ?? [] as $dfs) {
            $host = Domain::normalize((string) ($dfs['domain'] ?? ''));
            if ($host === '') {
                continue;
            }
            $key = self::crawlGraphKey($host, $cgKeys) ?? $host;
            $row = $rows[$key] ?? self::empty($key);

            if (!in_array(self::SOURCE_DATAFORSEO, $row['sources'], true)) {
                $row['sources'][] = self::SOURCE_DATAFORSEO;
                $row['nofollow'] = (bool) ($dfs['nofollow'] ?? false);
            } else {
                // Ayni kayit edilebilir domain'in ikinci host'u.
                $row['nofollow'] = $row['nofollow'] && (bool) ($dfs['nofollow'] ?? false);
            }
            $row['rank'] = self::max($row['rank'], $dfs['rank'] ?? null);
            $row['backlinks'] = ($row['backlinks'] ?? 0) + (int) ($dfs['backlinks'] ?? 0);
            $row['spam_score'] = self::max($row['spam_score'], $dfs['spam_score'] ?? null);
            $row['first_seen'] = self::minDate($row['first_seen'], $dfs['first_seen'] ?? null);
            $row['lost_date'] = self::maxDate($row['lost_date'], $dfs['lost_date'] ?? null);
            $rows[$key] = $row;
        }

        foreach ($bing ?? [] as $bg) {
            $host = Domain::normalize((string) ($bg['domain'] ?? ''));
            if ($host === '') {
                continue;
            }
            $key = self::crawlGraphKey($host, $cgKeys) ?? $host;
            $row = $rows[$key] ?? self::empty($key);

            if (!in_array(self::SOURCE_BING, $row['sources'], true)) {
                $row['sources'][] = self::SOURCE_BING;
            }
            $row['bing_links'] = ($row['bing_links'] ?? 0) + (int) ($bg['links'] ?? 0);
            $row['anchor'] ??= is_string($bg['anchor'] ?? null) && $bg['anchor'] !== '' ? $bg['anchor'] : null;
            $row['sample_url'] ??= is_string($bg['url'] ?? null) ? $bg['url'] : null;
            $rows[$key] = $row;
        }

        // Once DataForSEO rank'i olanlar (rank'e gore), sonra CrawlGraph
        // authority'si olanlar, en son yalnizca Bing'de gorunenler (link
        // sayisina gore) - olcekler birbiriyle karsilastirilamaz.
        usort($rows, fn (array $a, array $b) => [$b['rank'] !== null, $b['rank'] ?? -1, $b['authority'] ?? -1, $b['bing_links'] ?? -1, $a['domain']]
            <=> [$a['rank'] !== null, $a['rank'] ?? -1, $a['authority'] ?? -1, $a['bing_links'] ?? -1, $b['domain']]);

        return array_values($rows);
    }

    /**
     * @param  array<string, true>  $cgKeys
     */
    private static function crawlGraphKey(string $host, array $cgKeys): ?string
    {
        $labels = explode('.', $host);
        // En uzun eslesme: "a.b.ornek.com.tr" -> "b.ornek.com.tr" -> "ornek.com.tr" ...
        for ($i = 0, $n = count($labels); $i < $n - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            if (isset($cgKeys[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{domain: string, sources: list<string>, rank: ?int, backlinks: ?int, nofollow: bool, spam_score: ?int, first_seen: ?string, lost_date: ?string, authority: ?int, hosts: ?int, bing_links: ?int, anchor: ?string, sample_url: ?string}
     */
    private static function empty(string $domain): array
    {
        return [
            'domain' => $domain, 'sources' => [], 'rank' => null, 'backlinks' => null, 'nofollow' => false,
            'spam_score' => null, 'first_seen' => null, 'lost_date' => null, 'authority' => null, 'hosts' => null,
            'bing_links' => null, 'anchor' => null, 'sample_url' => null,
        ];
    }

    private static function max(?int $a, mixed $b): ?int
    {
        $b = is_numeric($b) ? (int) $b : null;

        return $a === null ? $b : ($b === null ? $a : max($a, $b));
    }

    private static function minDate(?string $a, mixed $b): ?string
    {
        $b = is_string($b) && $b !== '' ? $b : null;

        return $a === null ? $b : ($b === null ? $a : min($a, $b));
    }

    private static function maxDate(?string $a, mixed $b): ?string
    {
        $b = is_string($b) && $b !== '' ? $b : null;

        return $a === null ? $b : ($b === null ? $a : max($a, $b));
    }
}
