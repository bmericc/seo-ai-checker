<?php

declare(strict_types=1);

namespace App\Services\IndexNow;

use App\Models\Domain;
use App\Models\IndexNowSubmission;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;

/**
 * IndexNow protokolu (https://www.indexnow.org) ile yeni/degisen URL'leri
 * Bing, Yandex, Seznam, Naver gibi katilimci arama motorlarina aninda
 * bildirir. api.indexnow.org'a gonderilen bildirim tum katilimcilarla
 * paylasilir. Google IndexNow'u desteklemez.
 *
 * Arama motoru, bildirimin gercekten site sahibinden geldigini sitenin
 * kokundeki "{key}.txt" dosyasini okuyarak dogrular - bu yuzden kullanici
 * anahtar dosyasini once kendi sitesine yuklemelidir (bkz. verifyKeyFile).
 */
final class IndexNowService
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** Protokolun tek istekte izin verdigi en fazla URL sayisi. */
    public const MAX_URLS = 10000;

    public function __construct(private readonly Client $client)
    {
    }

    public function ensureKey(Domain $domain): string
    {
        if (is_string($domain->indexnow_key) && $domain->indexnow_key !== '') {
            return $domain->indexnow_key;
        }

        return $this->regenerateKey($domain);
    }

    public function regenerateKey(Domain $domain): string
    {
        // IndexNow: 8-128 karakter, yalnizca a-z A-Z 0-9 ve tire.
        $key = Str::lower(Str::random(32));
        $domain->forceFill(['indexnow_key' => $key, 'indexnow_verified_at' => null])->save();

        return $key;
    }

    public function host(Domain $domain): string
    {
        return (string) parse_url($domain->rootUrl(), PHP_URL_HOST);
    }

    public function keyFileUrl(Domain $domain): string
    {
        return $domain->rootUrl() . $this->ensureKey($domain) . '.txt';
    }

    /**
     * Anahtar dosyasinin sitede yayinda olup olmadigini kontrol eder ve
     * sonucu domain'e yazar.
     *
     * @return array{ok: bool, message: string}
     */
    public function verifyKeyFile(Domain $domain): array
    {
        $key = $this->ensureKey($domain);
        $url = $this->keyFileUrl($domain);

        try {
            $response = $this->client->get($url, ['http_errors' => false, 'timeout' => 15, 'allow_redirects' => ['max' => 3]]);
        } catch (GuzzleException $e) {
            $domain->forceFill(['indexnow_verified_at' => null])->save();

            return ['ok' => false, 'message' => __(':url adresine ulaşılamadı: :error', ['url' => $url, 'error' => $e->getMessage()])];
        }

        $ok = $response->getStatusCode() === 200 && trim((string) $response->getBody()) === $key;
        $domain->forceFill(['indexnow_verified_at' => $ok ? now() : null])->save();

        if ($ok) {
            return ['ok' => true, 'message' => __('Anahtar dosyası doğrulandı.')];
        }

        return $response->getStatusCode() === 200
            ? ['ok' => false, 'message' => __(':url bulundu ama içeriği anahtarla eşleşmiyor.', ['url' => $url])]
            : ['ok' => false, 'message' => __(':url bulunamadı (HTTP :status).', ['url' => $url, 'status' => $response->getStatusCode()])];
    }

    /**
     * Domain'in aktif (kaldirilmamis) sitemap URL'leri.
     *
     * @return list<string>
     */
    public function sitemapUrls(Domain $domain): array
    {
        return $domain->sitemapUrls()
            ->whereNull('removed_at')
            ->orderByDesc('last_seen_at')
            ->limit(self::MAX_URLS)
            ->pluck('url')
            ->all();
    }

    /**
     * URL'leri ayiklar (yalnizca domain'in kanonik host'una ait, tekrarsiz)
     * ve IndexNow'a gonderir. Gecerli URL kalmazsa istek atilmaz.
     *
     * @param  iterable<string>  $urls
     */
    public function submit(Domain $domain, iterable $urls, string $source): IndexNowSubmission
    {
        $host = $this->host($domain);
        $accepted = [];
        $skipped = 0;

        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            $parts = parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if (!in_array($scheme, ['http', 'https'], true) || strtolower((string) ($parts['host'] ?? '')) !== strtolower($host)) {
                $skipped++;

                continue;
            }

            $accepted[$url] = true;
        }

        $accepted = array_slice(array_keys($accepted), 0, self::MAX_URLS);

        if ($accepted === []) {
            return $domain->indexNowSubmissions()->create([
                'source' => $source,
                'url_count' => 0,
                'urls' => [],
                'ok' => false,
                'message' => __('Gönderilecek geçerli URL yok. URL\'ler :host adresine ait olmalı.', ['host' => $host]),
            ]);
        }

        $key = $this->ensureKey($domain);

        try {
            $response = $this->client->post(self::ENDPOINT, [
                'json' => [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => $this->keyFileUrl($domain),
                    'urlList' => $accepted,
                ],
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'http_errors' => false,
                'timeout' => 30,
            ]);
        } catch (GuzzleException $e) {
            return $domain->indexNowSubmissions()->create([
                'source' => $source,
                'url_count' => count($accepted),
                'urls' => $accepted,
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }

        $status = $response->getStatusCode();
        $message = $this->describeStatus($status);
        if ($skipped > 0) {
            $message .= ' ' . __(':count URL başka bir host\'a ait olduğu için atlandı.', ['count' => $skipped]);
        }

        return $domain->indexNowSubmissions()->create([
            'source' => $source,
            'url_count' => count($accepted),
            'urls' => $accepted,
            'status_code' => $status,
            'ok' => in_array($status, [200, 202], true),
            'message' => $message,
        ]);
    }

    private function describeStatus(int $status): string
    {
        return match ($status) {
            200 => __('URL\'ler başarıyla bildirildi.'),
            202 => __('Bildirim alındı; anahtar doğrulaması bekleniyor.'),
            400 => __('Geçersiz istek (HTTP 400).'),
            403 => __('Anahtar geçersiz: anahtar dosyası sitede bulunamadı ya da eşleşmiyor (HTTP 403).'),
            422 => __('URL\'ler bu host\'a ait değil ya da anahtar formatı hatalı (HTTP 422).'),
            429 => __('Çok fazla istek gönderildi, biraz sonra tekrar deneyin (HTTP 429).'),
            default => __('IndexNow beklenmeyen bir yanıt döndü (HTTP :status).', ['status' => $status]),
        };
    }
}
