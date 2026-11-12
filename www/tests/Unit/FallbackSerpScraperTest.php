<?php

namespace Tests\Unit;

use App\Services\Serp\AiOverviewResult;
use App\Services\Serp\FallbackSerpScraper;
use App\Services\Serp\OrganicResult;
use App\Services\Serp\SerpResult;
use App\Services\Serp\SerpScraper;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

class FallbackSerpScraperTest extends TestCase
{
    private function fakeScraper(callable|SerpResult $result, bool $mobile = false): SerpScraper
    {
        return new class ($result, $mobile) implements SerpScraper {
            public int $calls = 0;

            public function __construct(private $result, private bool $mobile)
            {
            }

            public function search(string $keyword, string $device = self::DEVICE_DESKTOP): SerpResult
            {
                $this->calls++;

                return is_callable($this->result) ? ($this->result)() : $this->result;
            }

            public function supportsMobile(): bool
            {
                return $this->mobile;
            }
        };
    }

    private function ok(string $domain): SerpResult
    {
        return new SerpResult('kw', [new OrganicResult(1, "https://{$domain}/", $domain, 't')], new AiOverviewResult(present: false));
    }

    private function blocked(string $reason): SerpResult
    {
        return new SerpResult('kw', [], new AiOverviewResult(present: false), blocked: true, blockReason: $reason);
    }

    public function test_returns_primary_result_without_calling_fallback(): void
    {
        $primary = $this->fakeScraper($this->ok('google.test'));
        $fallback = $this->fakeScraper($this->ok('dfs.test'));

        $result = (new FallbackSerpScraper($primary, $fallback))->search('kw');

        $this->assertSame(1, $result->positionOf('google.test'));
        $this->assertSame(0, $fallback->calls);
    }

    public function test_uses_fallback_when_primary_is_blocked(): void
    {
        $primary = $this->fakeScraper($this->blocked('CAPTCHA'));
        $fallback = $this->fakeScraper($this->ok('dfs.test'));

        $result = (new FallbackSerpScraper($primary, $fallback))->search('kw');

        $this->assertFalse($result->blocked);
        $this->assertSame(1, $result->positionOf('dfs.test'));
        $this->assertSame(1, $fallback->calls);
    }

    public function test_uses_fallback_when_primary_throws(): void
    {
        $primary = $this->fakeScraper(fn () => throw new ConnectException('timeout', new Request('GET', 'https://www.google.com')));
        $fallback = $this->fakeScraper($this->ok('dfs.test'));

        $result = (new FallbackSerpScraper($primary, $fallback))->search('kw');

        $this->assertSame(1, $result->positionOf('dfs.test'));
    }

    public function test_reports_both_reasons_when_both_fail(): void
    {
        $primary = $this->fakeScraper($this->blocked('CAPTCHA'));
        $fallback = $this->fakeScraper($this->blocked('Bakiye yetersiz'));

        $result = (new FallbackSerpScraper($primary, $fallback))->search('kw');

        $this->assertTrue($result->blocked);
        $this->assertStringContainsString('CAPTCHA', $result->blockReason);
        $this->assertStringContainsString('Bakiye yetersiz', $result->blockReason);
    }

    public function test_skips_primary_during_cooldown_after_block(): void
    {
        $primary = $this->fakeScraper($this->blocked('CAPTCHA'));
        $fallback = $this->fakeScraper($this->ok('dfs.test'));
        $scraper = new FallbackSerpScraper($primary, $fallback, new Repository(new ArrayStore()), 30);

        $scraper->search('kw');
        $scraper->search('kw2');

        $this->assertSame(1, $primary->calls);
        $this->assertSame(2, $fallback->calls);
    }

    public function test_no_cooldown_when_disabled(): void
    {
        $primary = $this->fakeScraper($this->blocked('CAPTCHA'));
        $fallback = $this->fakeScraper($this->ok('dfs.test'));
        $scraper = new FallbackSerpScraper($primary, $fallback, new Repository(new ArrayStore()), 0);

        $scraper->search('kw');
        $scraper->search('kw2');

        $this->assertSame(2, $primary->calls);
    }

    public function test_mobile_support_follows_primary(): void
    {
        $scraper = new FallbackSerpScraper($this->fakeScraper($this->ok('a.test')), $this->fakeScraper($this->ok('b.test'), mobile: true));

        $this->assertFalse($scraper->supportsMobile());
    }
}
