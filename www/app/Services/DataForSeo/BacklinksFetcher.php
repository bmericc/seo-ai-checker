<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

use Carbon\CarbonImmutable;

/**
 * Bir domain'in backlink profilini DataForSEO Backlinks API'sinden ceker:
 * ozet, en guclu referring domain'ler, anchor dagilimi ve son
 * NEW_LOST_DAYS gunde kazanilan / kaybedilen referring domain'ler.
 * https://docs.dataforseo.com/v3/backlinks/overview/
 *
 * Her cekim 5 live istektir (~0,02$ istek basina + satir basina kucuk
 * ucret, toplam ~0,13$). Ozet istegi basarisizsa tumu basarisiz sayilir;
 * liste istekleri ayri ayri hata verebilir, o zaman ilgili liste null
 * kalir ve hata mesajlari birlestirilir.
 */
final class BacklinksFetcher
{
    private const BASE = '/v3/backlinks/';

    public const REFERRING_DOMAINS_LIMIT = 100;

    public const ANCHORS_LIMIT = 50;

    public const NEW_LOST_LIMIT = 50;

    public const NEW_LOST_DAYS = 30;

    public function __construct(private readonly DataForSeoClient $client)
    {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     top_referring_domains: ?list<array<string, mixed>>,
     *     anchors: ?list<array<string, mixed>>,
     *     new_referring_domains: ?list<array<string, mixed>>,
     *     lost_referring_domains: ?list<array<string, mixed>>,
     *     cost: float,
     *     errors: list<string>,
     * }
     *
     * @throws DataForSeoException ozet istegi basarisizsa
     */
    public function fetch(string $target, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->subDays(self::NEW_LOST_DAYS)->format('Y-m-d 00:00:00');
        $cost = 0.0;
        $errors = [];

        $summaryTask = $this->client->postSingle(self::BASE . 'summary/live', [
            'target' => $target,
            'include_subdomains' => true,
            'internal_list_limit' => 10,
            'backlinks_status_type' => 'live',
        ]);
        $cost += (float) ($summaryTask['cost'] ?? 0);
        $summary = $summaryTask['result'][0] ?? [];

        $list = function (string $endpoint, array $params, callable $parse) use ($target, &$cost, &$errors): ?array {
            try {
                $task = $this->client->postSingle(self::BASE . $endpoint . '/live', ['target' => $target, 'include_subdomains' => true] + $params);
            } catch (DataForSeoException $e) {
                $errors[] = $e->getMessage();

                return null;
            }
            $cost += (float) ($task['cost'] ?? 0);
            $items = $task['result'][0]['items'] ?? [];

            return array_values(array_map($parse, array_filter(is_array($items) ? $items : [], 'is_array')));
        };

        $domainRow = fn (array $item): array => [
            'domain' => (string) ($item['domain'] ?? ''),
            'rank' => $this->int($item['rank'] ?? null),
            'backlinks' => $this->int($item['backlinks'] ?? null),
            'nofollow' => ($this->int($item['referring_pages_nofollow'] ?? null) ?? 0) > 0
                && $this->int($item['referring_pages_nofollow'] ?? null) === $this->int($item['referring_pages'] ?? null),
            'spam_score' => $this->int($item['backlinks_spam_score'] ?? null),
            'first_seen' => $this->date($item['first_seen'] ?? null),
            'lost_date' => $this->date($item['lost_date'] ?? null),
        ];

        return [
            'summary' => is_array($summary) ? $this->parseSummary($summary) : [],
            'top_referring_domains' => $list('referring_domains', [
                'limit' => self::REFERRING_DOMAINS_LIMIT,
                'order_by' => ['rank,desc'],
                'backlinks_status_type' => 'live',
            ], $domainRow),
            'anchors' => $list('anchors', [
                'limit' => self::ANCHORS_LIMIT,
                'order_by' => ['backlinks,desc'],
                'backlinks_status_type' => 'live',
            ], fn (array $item): array => [
                'anchor' => trim((string) ($item['anchor'] ?? '')),
                'backlinks' => $this->int($item['backlinks'] ?? null),
                'referring_domains' => $this->int($item['referring_domains'] ?? null),
                'first_seen' => $this->date($item['first_seen'] ?? null),
            ]),
            'new_referring_domains' => $list('referring_domains', [
                'limit' => self::NEW_LOST_LIMIT,
                'order_by' => ['first_seen,desc'],
                'backlinks_status_type' => 'all',
                'filters' => ['first_seen', '>', $since],
            ], $domainRow),
            'lost_referring_domains' => $list('referring_domains', [
                'limit' => self::NEW_LOST_LIMIT,
                'order_by' => ['lost_date,desc'],
                'backlinks_status_type' => 'lost',
                'filters' => ['lost_date', '>', $since],
            ], $domainRow),
            'cost' => round($cost, 4),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function parseSummary(array $summary): array
    {
        $tlds = is_array($summary['referring_links_tld'] ?? null) ? $summary['referring_links_tld'] : [];
        arsort($tlds);

        return [
            'rank' => $this->int($summary['rank'] ?? null),
            'backlinks' => $this->int($summary['backlinks'] ?? null),
            'spam_score' => $this->int($summary['backlinks_spam_score'] ?? null),
            'target_spam_score' => $this->int($summary['info']['target_spam_score'] ?? null),
            'referring_domains' => $this->int($summary['referring_domains'] ?? null),
            'referring_domains_nofollow' => $this->int($summary['referring_domains_nofollow'] ?? null),
            'referring_main_domains' => $this->int($summary['referring_main_domains'] ?? null),
            'referring_pages' => $this->int($summary['referring_pages'] ?? null),
            'referring_pages_nofollow' => $this->int($summary['referring_pages_nofollow'] ?? null),
            'referring_ips' => $this->int($summary['referring_ips'] ?? null),
            'broken_backlinks' => $this->int($summary['broken_backlinks'] ?? null),
            'first_seen' => $this->date($summary['first_seen'] ?? null),
            'top_tlds' => array_slice($tlds, 0, 8, true),
        ];
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? substr($value, 0, 10) : null;
    }
}
