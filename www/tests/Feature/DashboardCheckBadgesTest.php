<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainFact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ana ekrandaki (dashboard) domain listesinin, robots.txt/HTTPS/llms.txt/CrUX
 * gibi domain-genelinde paylasilan DomainFact alanlarini domain detay
 * sayfasiyla (bkz. DomainController::show) AYNI kaynaktan ve AYNI kapi
 * kosuluyla ($fact->checked_at || latestDomainCheck) gosterdigini dogrular.
 * DashboardController bir zamanlar sadece Domain'e ozel latestDomainCheck'i
 * yukleyip _check-badges partial'ine 'fact' degiskenini hic gecirmiyordu -
 * bu da listede DomainFact tazelendiginde bile eski/varsayilan "yok"
 * rozetlerinin takilip kalmasina, detay sayfasindaki guncel rozetlerle
 * uyusmamasina yol aciyordu.
 */
class DashboardCheckBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_https_badge_from_domain_fact_even_without_a_domain_check(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        DomainFact::query()->create([
            'domain' => 'example.com',
            'security_headers' => ['http_redirects_to_https' => true, 'headers' => []],
            'checked_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('HTTPS zorunlu');
        $response->assertDontSee('HTTPS yönlendirmesi yok');
    }

    public function test_dashboard_and_domain_show_page_agree_on_the_https_badge(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        DomainFact::query()->create([
            'domain' => 'example.com',
            'security_headers' => ['http_redirects_to_https' => true, 'headers' => []],
            'checked_at' => now(),
        ]);

        $dashboard = $this->actingAs($user)->get(route('dashboard'));
        $show = $this->actingAs($user)->get(route('domains.show', $domain));

        $dashboard->assertSee('HTTPS zorunlu');
        $show->assertSee('HTTPS zorunlu');
    }

    public function test_dashboard_falls_back_to_dash_when_neither_fact_nor_check_exist(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('HTTPS zorunlu');
        $response->assertDontSee('HTTPS yönlendirmesi yok');
    }
}
