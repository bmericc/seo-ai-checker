<?php

namespace Tests\Feature;

use App\Jobs\FetchDomainBacklinks;
use App\Models\BacklinkSnapshot;
use App\Models\Domain;
use App\Models\DomainMember;
use App\Models\User;
use App\Services\DataForSeo\BacklinksFetcher;
use App\Services\DataForSeo\DataForSeoClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DomainBacklinksTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $requests = [];

    /**
     * @return array{User, Domain}
     */
    private function ownerWithDomain(): array
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        return [$user, $domain];
    }

    private function envelope(array $result, int $taskStatus = 20000): Response
    {
        return new Response(200, [], json_encode([
            'status_code' => 20000,
            'tasks' => [[
                'status_code' => $taskStatus,
                'status_message' => $taskStatus === 20000 ? 'Ok.' : 'Boom.',
                'cost' => 0.02,
                'result' => $taskStatus === 20000 ? [$result] : null,
            ]],
        ]));
    }

    /**
     * @param  list<Response>  $responses
     */
    private function fakeFetcher(array $responses, string $login = 'l'): void
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));

        $this->app->instance(BacklinksFetcher::class, new BacklinksFetcher(
            new DataForSeoClient(new Client(['handler' => $stack]), $login, 'p'),
        ));
    }

    private function domainItem(string $domain, int $rank, ?string $lost = null): array
    {
        return [
            'domain' => $domain, 'rank' => $rank, 'backlinks' => 3, 'backlinks_spam_score' => 5,
            'referring_pages' => 3, 'referring_pages_nofollow' => 3,
            'first_seen' => '2026-11-02 07:17:56 +00:00', 'lost_date' => $lost,
        ];
    }

    private function successResponses(): array
    {
        return [
            $this->envelope([
                'target' => 'example.com', 'rank' => 42, 'backlinks' => 120, 'backlinks_spam_score' => 17,
                'referring_domains' => 10, 'referring_domains_nofollow' => 2, 'referring_main_domains' => 9,
                'referring_pages' => 80, 'referring_ips' => 7, 'broken_backlinks' => 1,
                'first_seen' => '2025-01-20 09:18:15 +00:00', 'info' => ['target_spam_score' => 15],
                'referring_links_tld' => ['com' => 5, 'com.tr' => 8],
            ]),
            $this->envelope(['items' => [$this->domainItem('strong.com', 300), $this->domainItem('weak.com', 10)]]),
            $this->envelope(['items' => [['anchor' => 'Özellikler', 'backlinks' => 3, 'referring_domains' => 2, 'first_seen' => '2025-01-20 09:18:15 +00:00']]]),
            $this->envelope(['items' => [$this->domainItem('fresh.com', 50)]]),
            $this->envelope(['items' => null]),
        ];
    }

    public function test_page_shows_empty_state_before_any_fetch(): void
    {
        [$user, $domain] = $this->ownerWithDomain();

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee('Henüz backlink verisi yok');
    }

    public function test_page_is_hidden_from_users_without_access(): void
    {
        [, $domain] = $this->ownerWithDomain();
        $stranger = User::factory()->create(['approved_at' => now()]);

        $this->actingAs($stranger)->get(route('domains.show', $domain))->assertForbidden();
    }

    public function test_refresh_queues_job_once_and_marks_domain(): void
    {
        Bus::fake([FetchDomainBacklinks::class]);
        $this->fakeFetcher([]);
        [$user, $domain] = $this->ownerWithDomain();

        $this->actingAs($user)->post(route('domains.backlinks.refresh', $domain))->assertRedirect(route('domains.show', $domain) . '#tab-backlinks');
        $this->actingAs($user)->post(route('domains.backlinks.refresh', $domain))->assertRedirect();

        $this->assertNotNull($domain->fresh()->backlinks_requested_at);
        Bus::assertDispatchedTimes(FetchDomainBacklinks::class, 1);
    }

    public function test_read_only_member_cannot_refresh(): void
    {
        Bus::fake([FetchDomainBacklinks::class]);
        [, $domain] = $this->ownerWithDomain();
        $member = User::factory()->create(['approved_at' => now()]);
        $domain->members()->create(['user_id' => $member->id, 'email' => $member->email, 'permission' => DomainMember::READ]);

        $this->actingAs($member)->get(route('domains.show', $domain))->assertOk()->assertDontSee(route('domains.backlinks.refresh', $domain));
        $this->actingAs($member)->post(route('domains.backlinks.refresh', $domain))->assertForbidden();
        Bus::assertNotDispatched(FetchDomainBacklinks::class);
    }

    public function test_job_stores_snapshot_and_page_renders_it(): void
    {
        $this->fakeFetcher($this->successResponses());
        [$user, $domain] = $this->ownerWithDomain();
        $domain->forceFill(['backlinks_requested_at' => now()])->save();

        FetchDomainBacklinks::dispatchSync($domain->id);

        $snapshot = $domain->backlinkSnapshots()->sole();
        $this->assertNull($snapshot->error);
        $this->assertSame(120, $snapshot->backlinks);
        $this->assertSame(10, $snapshot->referring_domains);
        $this->assertSame(42, $snapshot->rank);
        $this->assertSame('strong.com', $snapshot->top_referring_domains[0]['domain']);
        $this->assertTrue($snapshot->top_referring_domains[0]['nofollow']);
        $this->assertSame('Özellikler', $snapshot->anchors[0]['anchor']);
        $this->assertSame('fresh.com', $snapshot->new_referring_domains[0]['domain']);
        $this->assertSame([], $snapshot->lost_referring_domains);
        $this->assertSame(['com.tr' => 8, 'com' => 5], $snapshot->summary['top_tlds']);
        $this->assertNull($domain->fresh()->backlinks_requested_at);

        $this->assertCount(5, $this->requests);
        $newBody = json_decode((string) $this->requests[3]['request']->getBody(), true)[0];
        $this->assertSame('example.com', $newBody['target']);
        $this->assertSame('first_seen', $newBody['filters'][0]);

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee('strong.com')
            ->assertSee('fresh.com')
            ->assertSee('Özellikler');
    }

    public function test_failed_list_request_keeps_summary(): void
    {
        $responses = $this->successResponses();
        $responses[2] = $this->envelope([], 40000);
        $this->fakeFetcher($responses);
        [$user, $domain] = $this->ownerWithDomain();

        FetchDomainBacklinks::dispatchSync($domain->id);

        $snapshot = $domain->backlinkSnapshots()->sole();
        $this->assertNull($snapshot->error);
        $this->assertNull($snapshot->anchors);
        $this->assertNotEmpty($snapshot->summary['list_errors']);

        $this->actingAs($user)->get(route('domains.show', $domain))->assertOk()->assertSee('Bu liste alınamadı.');
    }

    public function test_failed_summary_stores_error_and_page_keeps_last_good_data(): void
    {
        [$user, $domain] = $this->ownerWithDomain();
        $domain->backlinkSnapshots()->create([
            'backlinks' => 50, 'referring_domains' => 5, 'summary' => [],
            'top_referring_domains' => [['domain' => 'old-friend.com', 'rank' => 1, 'backlinks' => 1, 'nofollow' => false, 'spam_score' => 0, 'first_seen' => null, 'lost_date' => null]],
            'anchors' => [], 'new_referring_domains' => [], 'lost_referring_domains' => [],
            'fetched_at' => now()->subWeek(),
        ]);
        $this->fakeFetcher([$this->envelope([], 40000)]);

        FetchDomainBacklinks::dispatchSync($domain->id);

        $this->assertSame(2, $domain->backlinkSnapshots()->count());
        $this->assertNotNull($domain->backlinkSnapshots()->latest('id')->first()->error);

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee('old-friend.com')
            ->assertSee('Son backlink çekimi başarısız oldu', false);
    }

    public function test_job_prunes_old_snapshots(): void
    {
        [, $domain] = $this->ownerWithDomain();
        for ($i = 0; $i < BacklinkSnapshot::KEEP_PER_DOMAIN; $i++) {
            $domain->backlinkSnapshots()->create(['error' => 'x', 'fetched_at' => now()->subWeeks(60 - $i)]);
        }
        $oldestId = $domain->backlinkSnapshots()->orderBy('fetched_at')->value('id');
        $this->fakeFetcher($this->successResponses());

        FetchDomainBacklinks::dispatchSync($domain->id);

        $this->assertSame(BacklinkSnapshot::KEEP_PER_DOMAIN, $domain->backlinkSnapshots()->count());
        $this->assertNull(BacklinkSnapshot::find($oldestId));
    }

    public function test_monthly_command_queues_every_domain(): void
    {
        Bus::fake([FetchDomainBacklinks::class]);
        $this->fakeFetcher([]);
        [$user, $domain] = $this->ownerWithDomain();
        Domain::query()->create(['domain' => 'second.com', 'user_id' => $user->id]);

        $this->artisan('backlinks:run-monthly-fetch')->assertSuccessful();

        Bus::assertDispatchedTimes(FetchDomainBacklinks::class, 2);
        $this->assertNotNull($domain->fresh()->backlinks_requested_at);
    }

    public function test_monthly_command_skips_without_dataforseo(): void
    {
        Bus::fake([FetchDomainBacklinks::class]);
        $this->fakeFetcher([], login: '');
        $this->ownerWithDomain();

        $this->artisan('backlinks:run-monthly-fetch')->assertSuccessful();

        Bus::assertNotDispatched(FetchDomainBacklinks::class);
    }
}
