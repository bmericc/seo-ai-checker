<?php

declare(strict_types=1);

namespace App\Services\Llms;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * /llms.txt dosyasinin varligini kontrol eder. NOT: Google'in kendi
 * kaynaklarina gore llms.txt su an icin AI arama sistemlerinde bir citation
 * faktoru degil (bkz. claude-seo'nun seo-geo skill'i) - bu yuzden sadece
 * "var/yok" bilgisi olarak raporlanir, skor/agirlik verilmez.
 */
final class LlmsTxtChecker
{
    public function __construct(private readonly Client $client)
    {
    }

    public function check(string $domainRootUrl): LlmsTxtResult
    {
        $url = rtrim($domainRootUrl, '/') . '/llms.txt';

        try {
            $response = $this->client->get($url);
        } catch (GuzzleException) {
            return new LlmsTxtResult(url: $url, found: false);
        }

        if ($response->getStatusCode() !== 200) {
            return new LlmsTxtResult(url: $url, found: false);
        }

        $body = trim((string) $response->getBody());

        return new LlmsTxtResult(url: $url, found: true, preview: $body !== '' ? mb_substr($body, 0, 200) : null);
    }
}
