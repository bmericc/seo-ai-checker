<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainFact;
use App\Models\User;
use App\Services\DomainCheckRunner;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bu testler, kullanicinin gercek dunyada anlattigi senaryoyu dogruluyor:
 * ayni domain'i (ornegin "tarti.com") iki farkli kullanici ayri ayri takip
 * ediyor. Biri (admin) domain'i tazeleyince, DIGERININ sayfasi da (kendi
 * kontrolu hic calismamis olsa bile) HEMEN guncel ortak bilgiyi gormeli -
 * eski SharedDomainCheckLookup modelinde bu hicbir zaman olmuyordu.
 */
class DomainCheckRunnerSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sitemap/GSC/GA4/Bing/keyword-suggester gibi domain'e ozel
        // servisler de gercek ag cagrisi yapar - hepsi ayni paylasilan
        // Client::class singleton'ini kullandigindan, genis bir bos-200
        // kuyrugu ile testi ag'dan bagimsiz ve hizli tutuyoruz.
        $responses = array_fill(0, 30, new Response(200, [], '{}'));
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    public function test_a_fresh_domain_fact_is_reused_without_rescanning(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $fact = DomainFact::forDomain('example.com');
        $fact->update([
            'ai_crawlers' => ['found' => true, 'url' => 'https://example.com/robots.txt', 'crawlers' => []],
            'canonical_host' => ['original_host' => 'example.com', 'canonical_host' => 'example.com', 'redirected' => false, 'redirect_status' => null],
            'checked_at' => now()->subDays(2),
        ]);

        $check = app(DomainCheckRunner::class)->run($domain);

        $this->assertSame(['found' => true, 'url' => 'https://example.com/robots.txt', 'crawlers' => []], $check->ai_crawlers);
        // fact'in checked_at'i degismedi - gercekten tekrar taranmadi.
        $this->assertTrue($fact->fresh()->checked_at->equalTo($fact->checked_at));
    }

    public function test_a_stale_domain_fact_is_rescanned_and_updated(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $fact = DomainFact::forDomain('example.com');
        $fact->update([
            'ai_crawlers' => ['found' => true, 'url' => 'https://old-scan.example.com/robots.txt', 'crawlers' => []],
            'checked_at' => now()->subDays(10),
        ]);

        app(DomainCheckRunner::class)->run($domain);

        $this->assertNotNull($fact->fresh()->checked_at);
        $this->assertTrue($fact->fresh()->checked_at->greaterThan(now()->subMinute()));
    }

    /**
     * Ana senaryo: iki farkli kullanici ayni domain string'ini takip
     * ediyor. B kullanicisi hic kontrol calistirmadi (kendi DomainCheck
     * gecmisi bos). A kullanicisi forceFresh ile kontrol calistirinca,
     * B'nin domain kaydinin ortak bilgisi (fact) de aninda guncellenmis
     * olmali - B kendi kontrolunu hic calistirmadan.
     */
    public function test_force_fresh_check_by_one_user_immediately_updates_the_shared_fact_for_a_sibling_domain_row(): void
    {
        $userA = User::factory()->create(['approved_at' => now()]);
        $userB = User::factory()->create(['approved_at' => now()]);
        $domainA = Domain::query()->create(['domain' => 'tarti.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'tarti.com', 'user_id' => $userB->id]);

        DomainFact::forDomain('tarti.com')->update([
            'ai_crawlers' => ['found' => true, 'url' => 'https://tarti.com/robots.txt', 'crawlers' => ['stale' => true]],
            'checked_at' => now()->subDays(1),
        ]);

        // A, kendi sitesinde bir sey duzeltip "Site Kontrolu Yap" ile
        // aciktan taze kontrol istiyor.
        app(DomainCheckRunner::class)->run($domainA, forceFresh: true);

        // B hicbir sey yapmadi ama kendi domain kaydinin "fact" iliskisi
        // (Domain::fact()) artik A'nin taze taramasini yansitiyor -
        // domain_id'si farkli olsa da domain STRING'i ayni oldugu icin.
        $this->assertFalse($domainB->fresh()->fact->ai_crawlers['crawlers']['stale'] ?? false);
    }

    public function test_two_domain_rows_for_the_same_string_share_one_fact_row(): void
    {
        $userA = User::factory()->create(['approved_at' => now()]);
        $userB = User::factory()->create(['approved_at' => now()]);
        $domainA = Domain::query()->create(['domain' => 'tarti.com', 'user_id' => $userA->id]);
        $domainB = Domain::query()->create(['domain' => 'tarti.com', 'user_id' => $userB->id]);

        $this->assertSame(0, DomainFact::query()->count());

        app(DomainCheckRunner::class)->run($domainA);
        app(DomainCheckRunner::class)->run($domainB);

        $this->assertSame(1, DomainFact::query()->count());
    }
}
