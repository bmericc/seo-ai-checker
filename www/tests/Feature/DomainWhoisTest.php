<?php

namespace Tests\Feature;

use App\Jobs\RunDomainWhoisLookup;
use App\Models\Domain;
use App\Models\DomainFact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainWhoisTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_domain_queues_a_whois_lookup(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($user)->post(route('domains.store'), ['domain' => 'example.com']);

        $domain = Domain::query()->where('domain', 'example.com')->firstOrFail();

        Queue::assertPushed(RunDomainWhoisLookup::class, fn (RunDomainWhoisLookup $job) => $job->domainId === $domain->id);
    }

    public function test_refresh_button_queues_a_whois_lookup_without_running_it_inline(): void
    {
        Queue::fake();

        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('domains.whois.refresh', $domain));

        $response->assertRedirect(route('domains.show', $domain));
        Queue::assertPushed(RunDomainWhoisLookup::class, fn (RunDomainWhoisLookup $job) => $job->domainId === $domain->id);
    }

    public function test_other_users_cannot_trigger_a_whois_refresh_for_a_domain_they_do_not_own(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['approved_at' => now()]);
        $other = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $owner->id]);

        $this->actingAs($other)->post(route('domains.whois.refresh', $domain))->assertForbidden();

        Queue::assertNotPushed(RunDomainWhoisLookup::class);
    }

    public function test_domain_page_shows_pending_state_before_any_whois_check_ran(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('WHOIS bilgisi henüz kuyruğa alınmadı');
    }

    public function test_domain_page_shows_age_and_registrar_once_whois_data_is_saved(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        DomainFact::forDomain('example.com')->update([
            'whois_registrar' => 'Example Registrar Inc.',
            'whois_registered_at' => now()->subYears(5),
            'whois_expires_at' => now()->addYear(),
            'whois_raw' => ['registrar' => 'Example Registrar Inc.'],
            'whois_checked_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('Example Registrar Inc.');
        $response->assertSee('5 yıl');
    }

    public function test_domain_page_shows_the_error_when_the_last_whois_lookup_failed(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.zzz', 'user_id' => $user->id]);
        DomainFact::forDomain('example.zzz')->update([
            'whois_error' => 'TLD .zzz is not supported.',
            'whois_checked_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('domains.show', $domain));

        $response->assertOk();
        $response->assertSee('TLD .zzz is not supported.');
    }

    /**
     * Ayni domain string'ini takip eden iki farkli kullanicidan biri hic
     * WHOIS sorgulamadi bile - digerinin WHOIS'i onceden kaydedilmisse
     * (paylasilan DomainFact uzerinden) her ikisi de ayni sonucu gormeli.
     */
    public function test_whois_data_is_shared_across_domain_rows_with_the_same_domain_string(): void
    {
        $userA = User::factory()->create(['approved_at' => now()]);
        $userB = User::factory()->create(['approved_at' => now()]);
        Domain::query()->create(['domain' => 'tarti.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'tarti.com', 'user_id' => $userB->id]);

        DomainFact::forDomain('tarti.com')->update([
            'whois_registrar' => 'Shared Registrar Inc.',
            'whois_checked_at' => now(),
        ]);

        $response = $this->actingAs($userB)->get(route('domains.show', $domainB));

        $response->assertOk();
        $response->assertSee('Shared Registrar Inc.');
    }
}
