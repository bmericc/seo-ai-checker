<?php

declare(strict_types=1);

namespace App\Services\Serp;

use App\Support\Domain;
use GuzzleHttp\Client;
use RuntimeException;

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
    private const BASE_URL = 'https://api.dataforseo.com/v3/serp/google/organic';
    private const ENDPOINT = self::BASE_URL . '/live/advanced';

    /** DataForSEO'nun tek task_post isteginde kabul ettigi en fazla gorev. */
    public const MAX_TASKS_PER_POST = 100;

    /** task_post basariyla olusturulan gorev. */
    private const STATUS_TASK_CREATED = 20100;

    /** task_get: gorev henuz islenmedi (Task Handed / Task in Queue). */
    private const PENDING_STATUSES = [40601, 40602];

    /** task_get: Google bu sorgu icin hic sonuc donmedi. */
    private const STATUS_NO_RESULTS = 40102;

    public function __construct(
        private readonly Client $client,
        private readonly string $login,
        private readonly string $password,
        private readonly int $locationCode = 2792,
        private readonly string $languageCode = 'tr',
        private readonly int $depth = 20,
    ) {
    }

    public function supportsMobile(): bool
    {
        return true;
    }

    public function search(string $keyword, string $device = self::DEVICE_DESKTOP): SerpResult
    {
        $response = $this->client->post(self::ENDPOINT, [
            'headers' => $this->headers(),
            'json' => [$this->taskPayload($keyword, $device)],
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

        return $this->resultFromTask($keyword, $task);
    }

    /**
     * Standard kuyruga (live'dan ~3 kat ucuz, sonuc birkac dakika icinde
     * hazir olur) gorev birakir. Ucret task_post aninda kesildigi icin bu
     * istek basarisiz olursa otomatik tekrar denenmemelidir.
     *
     * @param  list<array{keyword: string, device: string, tag: string}>  $tasks
     * @return array<string, string> tag => DataForSEO task id (reddedilen gorevler yer almaz)
     *
     * @throws RuntimeException
     */
    public function postTasks(array $tasks): array
    {
        if ($tasks === [] || count($tasks) > self::MAX_TASKS_PER_POST) {
            throw new RuntimeException(sprintf('task_post 1-%d görev kabul eder, %d verildi.', self::MAX_TASKS_PER_POST, count($tasks)));
        }

        $response = $this->client->post(self::BASE_URL . '/task_post', [
            'headers' => $this->headers(),
            'json' => array_map(
                fn (array $task) => $this->taskPayload($task['keyword'], $task['device']) + ['tag' => $task['tag']],
                $tasks,
            ),
            'http_errors' => false,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($body) || ($body['status_code'] ?? null) !== 20000) {
            throw new RuntimeException(sprintf(
                'DataForSEO task_post başarısız oldu: %s',
                is_array($body) ? ($body['status_message'] ?? 'bilinmeyen hata') : 'HTTP ' . $response->getStatusCode(),
            ));
        }

        $posted = [];
        foreach ($body['tasks'] ?? [] as $entry) {
            $tag = $entry['data']['tag'] ?? null;
            if (($entry['status_code'] ?? null) === self::STATUS_TASK_CREATED && is_string($entry['id'] ?? null) && is_string($tag)) {
                $posted[$tag] = $entry['id'];
            }
        }

        return $posted;
    }

    /**
     * Kuyruga birakilmis bir gorevin sonucunu alir (task_get ucretsizdir).
     * Gorev henuz hazir degilse null doner.
     */
    public function fetchTask(string $taskId, string $keyword): ?SerpResult
    {
        $response = $this->client->get(self::BASE_URL . '/task_get/advanced/' . rawurlencode($taskId), [
            'headers' => $this->headers(),
            'http_errors' => false,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($body) || ($body['status_code'] ?? null) !== 20000) {
            throw new RuntimeException(sprintf(
                'DataForSEO task_get başarısız oldu: %s',
                is_array($body) ? ($body['status_message'] ?? 'bilinmeyen hata') : 'HTTP ' . $response->getStatusCode(),
            ));
        }

        $task = $body['tasks'][0] ?? null;
        $taskStatus = is_array($task) ? ($task['status_code'] ?? null) : null;

        if (in_array($taskStatus, self::PENDING_STATUSES, true)) {
            return null;
        }

        if ($taskStatus === self::STATUS_NO_RESULTS) {
            return new SerpResult(keyword: $keyword, organicResults: [], aiOverview: new AiOverviewResult(present: false));
        }

        if ($taskStatus !== 20000) {
            return $this->blockedResult($keyword, sprintf(
                'DataForSEO görevi başarısız oldu: %s',
                is_array($task) ? ($task['status_message'] ?? "status_code {$taskStatus}") : 'görev yok',
            ));
        }

        return $this->resultFromTask($keyword, $task);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->login . ':' . $this->password),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(string $keyword, string $device): array
    {
        return [
            'keyword' => $keyword,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
            'device' => $device === self::DEVICE_MOBILE ? self::DEVICE_MOBILE : self::DEVICE_DESKTOP,
            'depth' => $this->depth,
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function resultFromTask(string $keyword, array $task): SerpResult
    {
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
