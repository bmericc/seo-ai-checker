<?php

declare(strict_types=1);

namespace App\Services\CrawlGraph;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * CrawlGraph API'si (https://crawlgraph.com/docs/api): Common Crawl'in acik
 * hiperlink grafiginden bir domain'e link veren domain'ler. Veri canli
 * degildir; arsivler (release) uc ayda bir yenilenir. Ucretsiz anahtar ayda
 * 15 backlink istegi verir; /releases kotadan dusmez.
 *
 * Kota bittiginde (HTTP 429) CrawlGraphQuotaException firlatilir ki
 * cagiran taraf ayni ay icinde baska domain denemeyi birakabilsin.
 */
final class CrawlGraphClient
{
    private const BASE_URL = 'https://crawlgraph.com/api/v1/';

    public function __construct(
        private readonly Client $client,
        private readonly ?string $apiKey,
        private readonly int $limit = 1000,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * En yeni sorgulanabilir arsiv (liste yeniden eskiye sirali gelir).
     *
     * @return ?array{id: string, label: ?string}
     *
     * @throws CrawlGraphException
     */
    public function latestRelease(): ?array
    {
        $body = $this->request('GET', 'releases');

        foreach ($body['releases'] ?? [] as $release) {
            if (is_array($release) && ($release['available'] ?? false) && is_string($release['id'] ?? null)) {
                return ['id' => $release['id'], 'label' => is_string($release['label'] ?? null) ? $release['label'] : null];
            }
        }

        return null;
    }

    /**
     * @return array{release_id: string, release_label: ?string, total: ?int, authority: ?int, rank: ?int, domains: list<array{domain: string, hosts: ?int, tld: ?string, authority: ?int}>}
     *
     * @throws CrawlGraphException
     */
    public function backlinks(string $domain, string $releaseId): array
    {
        $body = $this->request('POST', 'backlinks', ['json' => [
            'domain' => $domain,
            'release_id' => $releaseId,
            'limit' => $this->limit,
            'sort' => 'authority',
        ]]);

        $domains = [];
        foreach ($body['results'] ?? [] as $row) {
            if (!is_array($row) || !is_string($row['linking_domain'] ?? null)) {
                continue;
            }
            $domains[] = [
                'domain' => $row['linking_domain'],
                'hosts' => $this->int($row['num_hosts'] ?? null),
                'tld' => is_string($row['tld'] ?? null) ? $row['tld'] : null,
                'authority' => $this->int($row['cg_authority'] ?? null),
            ];
        }

        return [
            'release_id' => (string) ($body['release_id'] ?? $releaseId),
            'release_label' => is_string($body['release_label'] ?? null) ? $body['release_label'] : null,
            'total' => $this->int($body['total_linking_domains'] ?? null),
            'authority' => $this->int($body['cg_authority'] ?? null),
            'rank' => $this->int($body['cg_rank'] ?? null),
            'domains' => $domains,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws CrawlGraphException
     */
    private function request(string $method, string $path, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new CrawlGraphException('CrawlGraph yapılandırılmamış (CRAWLGRAPH_API_KEY).');
        }

        try {
            $response = $this->client->request($method, self::BASE_URL . $path, $options + [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Accept' => 'application/json'],
                'http_errors' => false,
                'timeout' => 60,
            ]);
        } catch (GuzzleException $e) {
            throw new CrawlGraphException(sprintf('CrawlGraph isteğine ulaşılamadı: %s', $e->getMessage()), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() === 429) {
            throw new CrawlGraphQuotaException($this->message($response, $body, 'CrawlGraph aylık kotası doldu.'));
        }

        if ($response->getStatusCode() !== 200 || !is_array($body)) {
            throw new CrawlGraphException($this->message($response, $body, sprintf('CrawlGraph isteği başarısız oldu (HTTP %d).', $response->getStatusCode())));
        }

        return $body;
    }

    private function message(ResponseInterface $response, mixed $body, string $fallback): string
    {
        $message = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : null;

        return $message !== null ? sprintf('%s (HTTP %d)', $message, $response->getStatusCode()) : $fallback;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
