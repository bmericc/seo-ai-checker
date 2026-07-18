<?php

namespace Tests\Unit;

use App\Models\DomainCheck;
use App\Services\Drift\DomainCheckDrift;
use PHPUnit\Framework\TestCase;

class DomainCheckDriftTest extends TestCase
{
    private function check(array $overrides = []): DomainCheck
    {
        return new DomainCheck(array_merge([
            'ai_crawlers' => ['found' => true, 'crawlers' => []],
            'sitemap' => ['found' => true, 'is_valid_xml' => true],
            'llms_txt' => ['found' => true],
            'security_headers' => ['headers' => [], 'http_redirects_to_https' => true],
            'canonical_host' => ['canonical_host' => 'example.com'],
            'crux' => ['metrics' => []],
        ], $overrides));
    }

    public function test_no_changes_between_identical_checks(): void
    {
        $current = $this->check();
        $previous = $this->check();

        $this->assertSame([], (new DomainCheckDrift())->diff($current, $previous));
    }

    public function test_newly_blocked_ai_crawler_is_reported(): void
    {
        $previous = $this->check(['ai_crawlers' => ['found' => true, 'crawlers' => [
            'GPTBot' => ['label' => 'OpenAI GPTBot', 'allowed' => true],
        ]]]);
        $current = $this->check(['ai_crawlers' => ['found' => true, 'crawlers' => [
            'GPTBot' => ['label' => 'OpenAI GPTBot', 'allowed' => false],
        ]]]);

        $changes = (new DomainCheckDrift())->diff($current, $previous);

        $this->assertTrue(collect($changes)->contains(fn ($c) => str_contains($c, 'GPTBot')));
    }

    public function test_sitemap_disappearing_is_reported(): void
    {
        $previous = $this->check(['sitemap' => ['found' => true, 'is_valid_xml' => true]]);
        $current = $this->check(['sitemap' => ['found' => false]]);

        $changes = (new DomainCheckDrift())->diff($current, $previous);

        $this->assertTrue(collect($changes)->contains(fn ($c) => str_contains($c, 'bulunamıyor')));
    }

    public function test_removed_security_header_is_reported(): void
    {
        $previous = $this->check(['security_headers' => ['headers' => ['Strict-Transport-Security' => 'max-age=1'], 'http_redirects_to_https' => true]]);
        $current = $this->check(['security_headers' => ['headers' => [], 'http_redirects_to_https' => true]]);

        $changes = (new DomainCheckDrift())->diff($current, $previous);

        $this->assertTrue(collect($changes)->contains(fn ($c) => str_contains($c, 'Kaldırılan') && str_contains($c, 'Strict-Transport-Security')));
    }

    public function test_canonical_host_change_is_reported(): void
    {
        $previous = $this->check(['canonical_host' => ['canonical_host' => 'example.com']]);
        $current = $this->check(['canonical_host' => ['canonical_host' => 'www.example.com']]);

        $changes = (new DomainCheckDrift())->diff($current, $previous);

        $this->assertTrue(collect($changes)->contains(fn ($c) => str_contains($c, 'example.com → www.example.com') || str_contains($c, 'Kanonik host değişti')));
    }

    public function test_crux_regression_is_reported(): void
    {
        $previous = $this->check(['crux' => ['metrics' => [
            'largest_contentful_paint' => ['label' => 'LCP', 'rating' => 'good'],
        ]]]);
        $current = $this->check(['crux' => ['metrics' => [
            'largest_contentful_paint' => ['label' => 'LCP', 'rating' => 'poor'],
        ]]]);

        $changes = (new DomainCheckDrift())->diff($current, $previous);

        $this->assertTrue(collect($changes)->contains(fn ($c) => str_contains($c, 'kötüleşti')));
    }

    public function test_crux_improvement_is_reported(): void
    {
        $previous = $this->check(['crux' => ['metrics' => [
            'largest_contentful_paint' => ['label' => 'LCP', 'rating' => 'poor'],
        ]]]);
        $current = $this->check(['crux' => ['metrics' => [
            'largest_contentful_paint' => ['label' => 'LCP', 'rating' => 'good'],
        ]]]);

        $changes = (new DomainCheckDrift())->diff($current, $previous);

        $this->assertTrue(collect($changes)->contains(fn ($c) => str_contains($c, 'iyileşti')));
    }
}
