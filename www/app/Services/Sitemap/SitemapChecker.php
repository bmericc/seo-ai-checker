<?php

declare(strict_types=1);

namespace App\Services\Sitemap;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Domainin varsayilan konumdaki (/sitemap.xml) XML sitemap'ini kontrol eder:
 * var mi, gecerli XML mi, normal sitemap mi yoksa sitemap index mi, kac
 * URL/alt-sitemap iceriyor - ve (sinirli sayida) sayfa URL'lerinin listesini
 * toplar, boylece bunlar veritabaninda takip edilebilir.
 */
final class SitemapChecker
{
    private const MAX_URLS = 500;

    private const MAX_SUB_SITEMAPS = 10;

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

        $xml = $this->parseXml($body);
        if ($xml === null) {
            return new SitemapResult(url: $url, found: true, isValidXml: false, error: 'XML parse edilemedi.');
        }

        $rootName = $xml->getName();
        $isIndex = $rootName === 'sitemapindex';

        if (!$isIndex && $rootName !== 'urlset') {
            return new SitemapResult(url: $url, found: true, isValidXml: false, error: sprintf('Beklenmeyen kok eleman: <%s>.', $rootName));
        }

        if (!$isIndex) {
            $urls = $this->extractLocs($xml, 'url');

            return new SitemapResult(
                url: $url,
                found: true,
                isValidXml: true,
                isSitemapIndex: false,
                urlCount: count($urls),
                urls: array_slice($urls, 0, self::MAX_URLS),
                urlsTruncated: count($urls) > self::MAX_URLS,
            );
        }

        $subSitemapUrls = $this->extractLocs($xml, 'sitemap');

        $collected = [];
        $truncated = count($subSitemapUrls) > self::MAX_SUB_SITEMAPS;
        foreach (array_slice($subSitemapUrls, 0, self::MAX_SUB_SITEMAPS) as $subUrl) {
            $subXml = $this->fetchXmlOrNull($subUrl);
            if ($subXml === null || $subXml->getName() !== 'urlset') {
                continue;
            }

            foreach ($this->extractLocs($subXml, 'url') as $pageUrl) {
                if (count($collected) >= self::MAX_URLS) {
                    $truncated = true;
                    break 2;
                }
                $collected[] = $pageUrl;
            }
        }

        return new SitemapResult(
            url: $url,
            found: true,
            isValidXml: true,
            isSitemapIndex: true,
            urlCount: count($subSitemapUrls),
            urls: $collected,
            urlsTruncated: $truncated,
        );
    }

    /**
     * @return string[]
     */
    private function extractLocs(\SimpleXMLElement $xml, string $entryName): array
    {
        $locs = [];
        foreach ($xml->{$entryName} as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $locs[] = $loc;
            }
        }

        return $locs;
    }

    /**
     * Alt-sitemap'ler icin "en iyi caba" fetch: herhangi bir sorunda (ag,
     * bos govde, gecersiz XML) sessizce null doner, tum kontrolu
     * durdurmaz.
     */
    private function fetchXmlOrNull(string $url): ?\SimpleXMLElement
    {
        try {
            $response = $this->client->get($url);
        } catch (GuzzleException) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = (string) $response->getBody();

        return trim($body) === '' ? null : $this->parseXml($body);
    }

    private function parseXml(string $body): ?\SimpleXMLElement
    {
        $previousErrorSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previousErrorSetting);

        return $xml === false ? null : $xml;
    }
}
