<?php

declare(strict_types=1);

namespace App\Services\Serp;

use App\Support\Domain;

final class SerpResult
{
    /**
     * @param OrganicResult[] $organicResults
     */
    public function __construct(
        public readonly string $keyword,
        public readonly array $organicResults,
        public readonly AiOverviewResult $aiOverview,
        public readonly bool $blocked = false,
        public readonly ?string $blockReason = null,
    ) {
    }

    /**
     * Standard kuyruktan gelen sonucun job'a/veritabanina tasinmasi icin
     * duz dizi hali (session/queue JSON serilestirmesi DTO'yu korumaz).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'organic_results' => array_map(
                static fn (OrganicResult $r) => ['position' => $r->position, 'url' => $r->url, 'domain' => $r->domain, 'title' => $r->title],
                $this->organicResults,
            ),
            'ai_overview' => [
                'present' => $this->aiOverview->present,
                'cited_domains' => $this->aiOverview->citedDomains,
                'note' => $this->aiOverview->note,
            ],
            'blocked' => $this->blocked,
            'block_reason' => $this->blockReason,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $aiOverview = is_array($data['ai_overview'] ?? null) ? $data['ai_overview'] : [];

        return new self(
            keyword: (string) ($data['keyword'] ?? ''),
            organicResults: array_map(
                static fn (array $r) => new OrganicResult(
                    position: (int) $r['position'],
                    url: (string) $r['url'],
                    domain: (string) $r['domain'],
                    title: (string) ($r['title'] ?? ''),
                ),
                array_values(array_filter($data['organic_results'] ?? [], 'is_array')),
            ),
            aiOverview: new AiOverviewResult(
                present: (bool) ($aiOverview['present'] ?? false),
                citedDomains: array_values($aiOverview['cited_domains'] ?? []),
                note: $aiOverview['note'] ?? null,
            ),
            blocked: (bool) ($data['blocked'] ?? false),
            blockReason: $data['block_reason'] ?? null,
        );
    }

    public function positionOf(string $domain): ?int
    {
        foreach ($this->organicResults as $result) {
            if (Domain::equals($result->domain, $domain)) {
                return $result->position;
            }
        }

        return null;
    }
}
