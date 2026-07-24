<?php

namespace Tests\Unit;

use App\Models\DomainFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainFactTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_domain_creates_a_row_if_none_exists(): void
    {
        $this->assertSame(0, DomainFact::query()->count());

        $fact = DomainFact::forDomain('example.com');

        $this->assertSame(1, DomainFact::query()->count());
        $this->assertSame('example.com', $fact->domain);
    }

    public function test_for_domain_returns_the_existing_row_instead_of_duplicating(): void
    {
        $original = DomainFact::forDomain('example.com');
        $original->update(['whois_registrar' => 'Example Registrar']);

        $again = DomainFact::forDomain('example.com');

        $this->assertSame($original->id, $again->id);
        $this->assertSame('Example Registrar', $again->whois_registrar);
        $this->assertSame(1, DomainFact::query()->count());
    }

    public function test_is_stale_when_never_checked(): void
    {
        $fact = DomainFact::forDomain('example.com');

        $this->assertTrue($fact->isStale());
    }

    public function test_is_stale_when_checked_more_than_seven_days_ago(): void
    {
        $fact = DomainFact::forDomain('example.com');
        $fact->update(['checked_at' => now()->subDays(8)]);

        $this->assertTrue($fact->isStale());
    }

    public function test_is_not_stale_within_seven_days(): void
    {
        $fact = DomainFact::forDomain('example.com');
        $fact->update(['checked_at' => now()->subDays(5)]);

        $this->assertFalse($fact->isStale());
    }

    public function test_whois_age_in_years_is_null_without_a_registration_date(): void
    {
        $fact = DomainFact::forDomain('example.com');

        $this->assertNull($fact->whoisAgeInYears());
    }

    public function test_whois_age_in_years_is_computed_from_registered_at(): void
    {
        $fact = DomainFact::forDomain('example.com');
        $fact->update(['whois_registered_at' => now()->subYears(5)]);

        $this->assertSame(5, $fact->whoisAgeInYears());
    }
}
