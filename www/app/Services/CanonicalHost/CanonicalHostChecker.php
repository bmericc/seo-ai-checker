<?php

declare(strict_types=1);

namespace App\Services\CanonicalHost;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Girilen host'un (ornegin "example.com") kalici (301/308) bir yonlendirme
 * ile baska bir host'a (ornegin "www.example.com") gidip gitmedigini tespit
 * eder. CrUX gibi origin bazli API'lar yonlendirmeyi takip etmez; gercek
 * trafigin gittigi host'u bulmak icin bu on kontrol gerekir.
 */
final class CanonicalHostChecker
{
    private const PERMANENT_REDIRECT_STATUSES = [301, 308];

    public function __construct(private readonly Client $client)
    {
    }

    public function check(string $host): CanonicalHostResult
    {
        try {
            $response = $this->client->get("https://{$host}/", ['allow_redirects' => false]);
        } catch (GuzzleException) {
            return new CanonicalHostResult(originalHost: $host, canonicalHost: $host, redirected: false);
        }

        $status = $response->getStatusCode();
        if (!in_array($status, self::PERMANENT_REDIRECT_STATUSES, true)) {
            return new CanonicalHostResult(originalHost: $host, canonicalHost: $host, redirected: false);
        }

        $targetHost = parse_url($response->getHeaderLine('Location'), PHP_URL_HOST);
        if (!is_string($targetHost) || $targetHost === '' || strcasecmp($targetHost, $host) === 0) {
            return new CanonicalHostResult(originalHost: $host, canonicalHost: $host, redirected: false);
        }

        return new CanonicalHostResult(
            originalHost: $host,
            canonicalHost: $targetHost,
            redirected: true,
            redirectStatus: $status,
        );
    }
}
