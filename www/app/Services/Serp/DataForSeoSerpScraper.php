<?php

declare(strict_types=1);

namespace App\Services\Serp;

use App\Support\Domain;
use GuzzleHttp\Client;

/**
 * Google SERP'i dogrudan kazimak yerine DataForSEO'nun "Live Advanced"
 * Google Organic uc noktasini kullanir (bkz.
 * https://docs.dataforseo.com/v3/serp-google-organic-live-advanced/).
 * DataForSEO kendi tarafinda proxy/CAPTCHA/oturum yonetimini yapip temiz
 * JSON dondugu icin GoogleSerpScraper'i surekli engelleyen "unusual
 * traffic"/CAPTCHA sorununu koklu cozer - ucret karsiligi.
 *
 * ai_overview oge semasi DataForSEO'nun resmi dokumantasyonunda ayrintili
 * belgelenmemis; bu yuzden kaynak domain cikarimi best-effort'tur (birden
 * fazla olasi alan adi denenir), basarisiz olursa GoogleSerpScraper'daki
 * ayni "tespit edildi ama kaynaklar cikarilamadi" geri bildirimini verir.
 */
final class DataForSeoSerpScraper implements SerpScraper
{
    private const ENDPOINT = 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced';

    public function __construct(
        private readonly Client $client,
        private readonly string $login,
        private readonly string $password,
        private readonly int $locationCode = 2792,
        private readonly string $languageCode = 'tr',
        private readonly int $depth = 20,
    ) {
    }

    public function search(string $keyword): SerpResult
    {
        $response = $this->client->post(self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->login . ':' . $this->password),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                [
                    'keyword' => $keyword,
                    'location_code' => $this->locationCode,
                    'language_code' => $this->languageCode,
                    'device' => 'desktop',
                    'depth' => $this->depth,
                ],
            ],
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        if ($status !== 200 || !is_array($body)) {
            return $this->blockedResult($keyword, sprintf('DataForSEO isteği başarısız oldu (HTTP %d).', $status));
        }

        $topStatus = $body['status_code'] ?? null;
        if ($topStatus !== 20000) {
            return $this->blockedResult($keyword, sprintf(
                'DataForSEO hata döndürdü: %s',
                $body['status_message'] ?? "status_code {$topStatus}"
            ));
        }

        $task = $body['tasks'][0] ?? null;
        $taskStatus = $task['status_code'] ?? null;
        if ($task === null || $taskStatus !== 20000) {
            return $this->blockedResult($keyword, sprintf(
                'DataForSEO görevi başarısız oldu: %s',
                $task['status_message'] ?? "status_code {$taskStatus}"
            ));
        }

        $items = $task['result'][0]['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        return new SerpResult(
            keyword: $keyword,
            organicResults: $this->parseOrganicResults($items),
            aiOverview: $this->parseAiOverview($items),
        );
    }

    private function blockedResult(string $keyword, string $reason): SerpResult
    {
        return new SerpResult(
            keyword: $keyword,
            organicResults: [],
            aiOverview: new AiOverviewResult(present: false),
            blocked: true,
            blockReason: $reason,
        );
    }

    /**
     * @param  array<int, mixed>  $items
     * @return OrganicResult[]
     */
    private function parseOrganicResults(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'organic') {
                continue;
            }

            $url = $item['url'] ?? null;
            $domain = $item['domain'] ?? ($url !== null ? Domain::fromUrl($url) : null);
            $position = $item['rank_absolute'] ?? $item['rank_group'] ?? null;

            if (!is_string($url) || !is_string($domain) || !is_int($position)) {
                continue;
            }

            $results[] = new OrganicResult(
                position: $position,
                url: $url,
                domain: $domain,
                title: is_string($item['title'] ?? null) ? $item['title'] : '',
            );
        }

        usort($results, static fn (OrganicResult $a, OrganicResult $b) => $a->position <=> $b->position);

        return $results;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function parseAiOverview(array $items): AiOverviewResult
    {
        $aiOverviewItem = null;
        foreach ($items as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'ai_overview') {
                $aiOverviewItem = $item;
                break;
            }
        }

        if ($aiOverviewItem === null) {
            return new AiOverviewResult(present: false);
        }

        $citedDomains = $this->extractAiOverviewDomains($aiOverviewItem);

        $note = $citedDomains === []
            ? 'AI Overview tespit edildi fakat içindeki kaynak linkleri otomatik olarak çıkarılamadı; sayfayı manuel kontrol edin.'
            : null;

        return new AiOverviewResult(present: true, citedDomains: $citedDomains, note: $note);
    }

    /**
     * DataForSEO'nun ai_overview oge semasinda kaynak linkleri hangi anahtar
     * altinda geldigi resmi olarak ayrintili belgelenmedigi icin birden
     * fazla olasi alani deniyoruz (references/items/links - her biri url
     * alanli nesne dizisi ya da duz string URL dizisi olabilir).
     *
     * @param  array<string, mixed>  $aiOverviewItem
     * @return string[]
     */
    private function extractAiOverviewDomains(array $aiOverviewItem): array
    {
        $domains = [];

        foreach (['references', 'items', 'links'] as $key) {
            $entries = $aiOverviewItem[$key] ?? null;
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                $url = is_string($entry) ? $entry : ($entry['url'] ?? null);
                if (!is_string($url)) {
                    continue;
                }

                $domain = Domain::fromUrl($url);
                if ($domain !== null) {
                    $domains[$domain] = true;
                }
            }
        }

        return array_keys($domains);
    }
}
