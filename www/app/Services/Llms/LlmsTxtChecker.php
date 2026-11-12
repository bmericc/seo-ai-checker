<?php

declare(strict_types=1);

namespace App\Services\Llms;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * /llms.txt dosyasinin varligini kontrol eder. NOT: Google'in kendi
 * kaynaklarina gore llms.txt su an icin AI arama sistemlerinde bir citation
 * faktoru degil (bkz. claude-seo'nun seo-geo skill'i) - bu yuzden sadece
 * "var/yok" bilgisi olarak raporlanir, skor/agirlik verilmez. Bulunan
 * dosya ayrica LlmsTxtValidator ile bicim acisindan denetlenir.
 */
final class LlmsTxtChecker
{
    public function __construct(
        private readonly Client $client,
        private readonly LlmsTxtValidator $validator = new LlmsTxtValidator(),
    ) {
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

        return new LlmsTxtResult(
            url: $url,
            found: true,
            preview: $body !== '' ? mb_substr($body, 0, 200) : null,
            validation: $this->validator->validate($body, $response->getHeaderLine('Content-Type') ?: null, (string) parse_url($domainRootUrl, PHP_URL_HOST)),
        );
    }
}
