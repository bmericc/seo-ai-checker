<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RunDomainSiteCheck;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\User;
use App\Services\DomainCheckRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunDomainSiteCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * DomainCheckRunner is a final class composed of many HTTP-calling
     * checkers (robots.txt, sitemap, security headers, CrUX, ...), so it
     * can't be Mockery-mocked (final classes can't be subclassed). Instead
     * this fakes every outbound HTTP call with a generic empty response and
     * asserts the job's real, observable effect: a DomainCheck row gets
     * persisted for the domain.
     */
    public function test_it_runs_the_domain_check_runner_for_the_given_domain(): void
    {
        Http::fake(fn () => Http::response('', 200));

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        (new RunDomainSiteCheck($domain->id))->handle($this->app->make(DomainCheckRunner::class));

        $this->assertTrue(DomainCheck::query()->where('domain_id', $domain->id)->exists());
    }

    public function test_it_does_nothing_when_the_domain_no_longer_exists(): void
    {
        Http::fake(fn () => Http::response('', 200));

        (new RunDomainSiteCheck(999999))->handle($this->app->make(DomainCheckRunner::class));

        $this->assertSame(0, DomainCheck::query()->count());
        Http::assertNothingSent();
    }
}
