<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use App\Services\SharedDomainCheckLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedDomainCheckLookupTest extends TestCase
{
    use RefreshDatabase;

    private function domainCheckPayload(): array
    {
        return [
            'ai_crawlers' => ['found' => true],
            'sitemap' => ['found' => true],
            'llms_txt' => ['found' => false],
            'security_headers' => ['reachable' => true],
            'canonical_host' => ['canonical_host' => 'example.com'],
            'crux' => ['configured' => false],
        ];
    }

    public function test_finds_a_recent_check_from_a_different_users_domain_row_with_the_same_domain_string(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $domainA = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);

        $sharedCheck = $domainA->domainChecks()->create($this->domainCheckPayload());

        $found = (new SharedDomainCheckLookup())->find($domainB);

        $this->assertNotNull($found);
        $this->assertSame($sharedCheck->id, $found->id);
    }

    public function test_ignores_checks_belonging_to_the_domains_own_row(): void
    {
        $user = User::factory()->create();
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->domainChecks()->create($this->domainCheckPayload());

        $found = (new SharedDomainCheckLookup())->find($domain);

        $this->assertNull($found);
    }

    public function test_ignores_checks_for_a_different_domain_string(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $domainA = Domain::query()->create(['domain' => 'other.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);

        $domainA->domainChecks()->create($this->domainCheckPayload());

        $found = (new SharedDomainCheckLookup())->find($domainB);

        $this->assertNull($found);
    }

    public function test_ignores_checks_older_than_the_reuse_window(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $domainA = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);

        $check = $domainA->domainChecks()->create($this->domainCheckPayload());
        $check->forceFill(['created_at' => now()->subHours(7)])->save();

        $found = (new SharedDomainCheckLookup())->find($domainB);

        $this->assertNull($found);
    }

    public function test_picks_the_most_recent_shared_check_when_multiple_exist(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $domainA = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userB->id]);
        $domainC = Domain::query()->create(['domain' => 'example.com', 'user_id' => $userC->id]);

        $older = $domainA->domainChecks()->create($this->domainCheckPayload());
        $older->forceFill(['created_at' => now()->subHours(2)])->save();
        $newer = $domainB->domainChecks()->create($this->domainCheckPayload());

        $found = (new SharedDomainCheckLookup())->find($domainC);

        $this->assertSame($newer->id, $found->id);
    }
}
