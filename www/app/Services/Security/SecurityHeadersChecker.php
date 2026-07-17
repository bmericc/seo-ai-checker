<?php

declare(strict_types=1);

namespace App\Services\Security;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Domainin guvenlik header'larini (CSP, HSTS, X-Frame-Options,
 * X-Content-Type-Options, Referrer-Policy) ve HTTP->HTTPS yonlendirmesini
 * kontrol eder.
 */
final class SecurityHeadersChecker
{
    private const HEADERS = [
        'Content-Security-Policy',
        'Strict-Transport-Security',
        'X-Frame-Options',
        'X-Content-Type-Options',
        'Referrer-Policy',
    ];

    public function __construct(private readonly Client $client)
    {
    }

    public function check(string $domainHost): SecurityHeadersResult
    {
        try {
            $response = $this->client->get("https://{$domainHost}/");
        } catch (GuzzleException) {
            return new SecurityHeadersResult(reachable: false);
        }

        $headers = [];
        foreach (self::HEADERS as $name) {
            $value = $response->getHeaderLine($name);
            $headers[$name] = $value !== '' ? $value : null;
        }

        return new SecurityHeadersResult(
            reachable: true,
            headers: $headers,
            httpRedirectsToHttps: $this->httpRedirectsToHttps($domainHost),
        );
    }

    private function httpRedirectsToHttps(string $domainHost): bool
    {
        try {
            $response = $this->client->get("http://{$domainHost}/", ['allow_redirects' => false]);
        } catch (GuzzleException) {
            return false;
        }

        $status = $response->getStatusCode();
        if ($status < 300 || $status >= 400) {
            return false;
        }

        return str_starts_with($response->getHeaderLine('Location'), 'https://');
    }
}
