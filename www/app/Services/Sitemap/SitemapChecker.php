<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Domainin varsayilan konumdaki (/sitemap.xml) XML sitemap'ini kontrol eder:
 * var mi, gecerli XML mi, normal sitemap mi yoksa sitemap index mi, kac
 * URL/alt-sitemap iceriyor.
 */
final class SitemapChecker
{
    public function __construct(private readonly Client $client)
    {
    }

    public function check(string $domainRootUrl): SitemapResult
    {
        $url = rtrim($domainRootUrl, '/') . '/sitemap.xml';

        try {
            $response = $this->client->get($url);
        } catch (GuzzleException) {
            return new SitemapResult(url: $url, found: false);
        }

        if ($response->getStatusCode() !== 200) {
            return new SitemapResult(url: $url, found: false);
        }

        $body = (string) $response->getBody();
        if (trim($body) === '') {
            return new SitemapResult(url: $url, found: true, isValidXml: false, error: 'Bos yanit.');
        }

        $previousErrorSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previousErrorSetting);

        if ($xml === false) {
            return new SitemapResult(url: $url, found: true, isValidXml: false, error: 'XML parse edilemedi.');
        }

        $rootName = $xml->getName();
        $isIndex = $rootName === 'sitemapindex';
        $count = $isIndex ? $xml->sitemap->count() : $xml->url->count();

        if (!$isIndex && $rootName !== 'urlset') {
            return new SitemapResult(url: $url, found: true, isValidXml: false, error: sprintf('Beklenmeyen kok eleman: <%s>.', $rootName));
        }

        return new SitemapResult(
            url: $url,
            found: true,
            isValidXml: true,
            isSitemapIndex: $isIndex,
            urlCount: $count,
        );
    }
}
