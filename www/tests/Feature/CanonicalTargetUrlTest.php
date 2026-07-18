<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalTargetUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_root_url_falls_back_to_the_entered_domain_when_no_check_has_run(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);

        $this->assertSame('https://example.com/', $domain->rootUrl());
    }

    public function test_domain_root_url_uses_the_canonical_host_when_a_redirect_is_known(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $domain->domainChecks()->create([
            'canonical_host' => [
                'original_host' => 'example.com',
                'canonical_host' => 'www.example.com',
                'redirected' => true,
                'redirect_status' => 301,
            ],
        ]);

        $this->assertSame('https://www.example.com/', $domain->fresh()->rootUrl());
    }

    public function test_keyword_without_its_own_url_targets_the_domains_canonical_root(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $domain->domainChecks()->create([
            'canonical_host' => [
                'original_host' => 'example.com',
                'canonical_host' => 'www.example.com',
                'redirected' => true,
                'redirect_status' => 301,
            ],
        ]);
        $keyword = $domain->keywords()->create(['keyword' => 'seo']);

        $this->assertSame('https://www.example.com/', $keyword->fresh()->targetUrl());
    }

    public function test_keyword_with_its_own_url_ignores_the_canonical_host(): void
    {
        $domain = Domain::query()->create(['domain' => 'example.com']);
        $domain->domainChecks()->create([
            'canonical_host' => [
                'original_host' => 'example.com',
                'canonical_host' => 'www.example.com',
                'redirected' => true,
                'redirect_status' => 301,
            ],
        ]);
        $keyword = $domain->keywords()->create(['keyword' => 'seo', 'url' => 'https://example.com/blog/post']);

        $this->assertSame('https://example.com/blog/post', $keyword->fresh()->targetUrl());
    }
}
