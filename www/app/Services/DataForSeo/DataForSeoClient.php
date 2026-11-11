<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * DataForSEO v3 API'si icin ortak HTTP katmani: Basic auth, JSON govde ve
 * zarf (envelope) kontrolu. Her v3 cevabi ayni sekildedir:
 * {status_code: 20000, tasks: [{status_code, status_message, cost, result}]}.
 * Ust seviye status_code 20000 degilse istek tamamen basarisizdir; gorev
 * seviyesindeki status_code'lar ise cagiran servis tarafindan yorumlanir
 * (ornegin task_post icin 20100 "Task Created", task_get icin 40601/40602
 * "henuz hazir degil").
 *
 * Ucretlendirilen bir istek belirsiz bir 5xx sonrasinda asla otomatik
 * tekrarlanmaz - cift ucretlendirmeyi onlemek icin bu sinifta retry yoktur.
 */
final class DataForSeoClient
{
    private const BASE_URL = 'https://api.dataforseo.com';

    public function __construct(
        private readonly Client $client,
        private readonly ?string $login,
        private readonly ?string $password,
        private readonly int $timeoutSeconds = 150,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->login !== null && $this->login !== '' && $this->password !== null && $this->password !== '';
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>> cevaptaki tasks dizisi
     */
    public function post(string $path, array $tasks): array
    {
        return $this->request('POST', $path, ['json' => $tasks]);
    }

    /**
     * @return list<array<string, mixed>> cevaptaki tasks dizisi
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path, []);
    }

    /**
     * Tek gorevli bir live istegin ilk gorevini dondurur; gorev basarisizsa
     * (status_code 20000 degilse) DataForSeoException firlatir.
     *
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public function postSingle(string $path, array $task): array
    {
        $tasks = $this->post($path, [$task]);
        $first = $tasks[0] ?? null;

        if (!is_array($first)) {
            throw new DataForSeoException('DataForSEO boş görev listesi döndürdü.');
        }

        if (($first['status_code'] ?? null) !== 20000) {
            throw new DataForSeoException(sprintf(
                'DataForSEO görevi başarısız oldu: %s',
                $first['status_message'] ?? 'status_code ' . ($first['status_code'] ?? '?'),
            ));
        }

        return $first;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    private function request(string $method, string $path, array $options): array
    {
        if (!$this->isConfigured()) {
            throw new DataForSeoException('DataForSEO yapılandırılmamış (DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD).');
        }

        try {
            $response = $this->client->request($method, self::BASE_URL . $path, $options + [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->login . ':' . $this->password),
                    'Content-Type' => 'application/json',
                ],
                'http_errors' => false,
                'timeout' => $this->timeoutSeconds,
            ]);
        } catch (GuzzleException $e) {
            throw new DataForSeoException(sprintf('DataForSEO isteğine ulaşılamadı: %s', $e->getMessage()), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        if ($status !== 200 || !is_array($body)) {
            throw new DataForSeoException(sprintf('DataForSEO isteği başarısız oldu (HTTP %d).', $status));
        }

        if (($body['status_code'] ?? null) !== 20000) {
            throw new DataForSeoException(sprintf(
                'DataForSEO hata döndürdü: %s',
                $body['status_message'] ?? 'status_code ' . ($body['status_code'] ?? '?'),
            ));
        }

        $tasks = $body['tasks'] ?? [];

        return is_array($tasks) ? array_values(array_filter($tasks, 'is_array')) : [];
    }
}
