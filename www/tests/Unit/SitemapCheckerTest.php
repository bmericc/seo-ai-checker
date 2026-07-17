<?php

namespace Tests\Unit;

use App\Services\Sitemap\SitemapChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SitemapCheckerTest extends TestCase
{
    private function checkerWithBody(string $body, int $status = 200): SitemapChecker
    {
        $mock = new MockHandler([new Response($status, [], $body)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new SitemapChecker($client);
    }

    public function test_valid_urlset_sitemap_is_parsed(): void
    {
        $checker = $this->checkerWithBody(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>https://example.com/</loc></url>
              <url><loc>https://example.com/about</loc></url>
            </urlset>
            XML);

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->found);
        $this->assertTrue($result->isValidXml);
        $this->assertFalse($result->isSitemapIndex);
        $this->assertSame(2, $result->urlCount);
    }

    public function test_sitemap_index_is_detected(): void
    {
        $checker = $this->checkerWithBody(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <sitemap><loc>https://example.com/sitemap-pages.xml</loc></sitemap>
              <sitemap><loc>https://example.com/sitemap-posts.xml</loc></sitemap>
              <sitemap><loc>https://example.com/sitemap-images.xml</loc></sitemap>
            </sitemapindex>
            XML);

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->isSitemapIndex);
        $this->assertSame(3, $result->urlCount);
    }

    public function test_missing_sitemap_reports_not_found(): void
    {
        $checker = $this->checkerWithBody('Not Found', 404);

        $result = $checker->check('https://example.com/');

        $this->assertFalse($result->found);
    }

    public function test_invalid_xml_is_flagged(): void
    {
        $checker = $this->checkerWithBody('<not><valid</not>');

        $result = $checker->check('https://example.com/');

        $this->assertTrue($result->found);
        $this->assertFalse($result->isValidXml);
        $this->assertNotNull($result->error);
    }
}
