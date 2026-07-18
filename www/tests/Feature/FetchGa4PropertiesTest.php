<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchGa4PropertiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_connected_user_gets_the_property_list_flashed_and_can_select_it(): void
    {
        $user = User::factory()->create([
            'approved_at' => now(),
            'google_refresh_token' => 'refresh-token',
            'google_access_token' => 'still-valid-token',
            'google_token_expires_at' => now()->addHour(),
        ]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->domainChecks()->create([
            'ai_crawlers' => ['found' => false],
            'sitemap' => ['found' => false],
            'llms_txt' => ['found' => false],
            'security_headers' => ['reachable' => false],
            'canonical_host' => ['canonical_host' => 'example.com'],
            'crux' => ['configured' => false],
            'gsc' => ['configured' => false],
            'ga4' => ['configured' => false],
            'bing_backlinks' => ['configured' => false],
        ]);

        $body = json_encode(['accountSummaries' => [
            ['displayName' => 'Acme', 'propertySummaries' => [
                ['property' => 'properties/999', 'displayName' => 'example.com'],
            ]],
        ]]);
        $mock = new MockHandler([new Response(200, [], $body)]);
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create($mock)]));

        $response = $this->actingAs($user)->post(route('domains.ga4-properties.fetch', $domain));

        $response->assertRedirect(route('domains.show', $domain));
        $response->assertSessionHas('ga4Properties');

        $showResponse = $this->actingAs($user)->get(route('domains.show', $domain));
        $showResponse->assertSee('Acme');
        $showResponse->assertSee('example.com (999)');
    }

    public function test_show_page_renders_a_two_step_account_then_property_select(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);
        $domain->domainChecks()->create([
            'ai_crawlers' => ['found' => false],
            'sitemap' => ['found' => false],
            'llms_txt' => ['found' => false],
            'security_headers' => ['reachable' => false],
            'canonical_host' => ['canonical_host' => 'example.com'],
            'crux' => ['configured' => false],
            'gsc' => ['configured' => false],
            'ga4' => ['configured' => false],
            'bing_backlinks' => ['configured' => false],
        ]);

        $response = $this->actingAs($user)->withSession(['ga4Properties' => [
            ['propertyId' => '111', 'label' => 'acme.com', 'accountName' => 'Acme Inc'],
            ['propertyId' => '222', 'label' => 'shop.acme.com', 'accountName' => 'Acme Inc'],
            ['propertyId' => '333', 'label' => 'blog.example.com', 'accountName' => 'Personal'],
        ]])->get(route('domains.show', $domain));

        $response->assertSee('data-ga4-account-select', false);
        $response->assertSee('data-ga4-property-select', false);
        $response->assertSee('Acme Inc (2)');
        $response->assertSee('Personal (1)');
        $response->assertSee('data-account="Acme Inc"', false);
        $response->assertSee('data-account="Personal"', false);
    }

    public function test_saved_property_preselects_its_account_on_reload(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id, 'ga4_property_id' => '333']);
        $domain->domainChecks()->create([
            'ai_crawlers' => ['found' => false],
            'sitemap' => ['found' => false],
            'llms_txt' => ['found' => false],
            'security_headers' => ['reachable' => false],
            'canonical_host' => ['canonical_host' => 'example.com'],
            'crux' => ['configured' => false],
            'gsc' => ['configured' => false],
            'ga4' => ['configured' => false],
            'bing_backlinks' => ['configured' => false],
        ]);

        $response = $this->actingAs($user)->withSession(['ga4Properties' => [
            ['propertyId' => '111', 'label' => 'acme.com', 'accountName' => 'Acme Inc'],
            ['propertyId' => '333', 'label' => 'blog.example.com', 'accountName' => 'Personal'],
        ]])->get(route('domains.show', $domain));

        preg_match('/<select[^>]*data-ga4-account-select[^>]*>(.*?)<\/select>/s', $response->getContent(), $accountMatch);
        preg_match('/<select[^>]*data-ga4-property-select[^>]*>(.*?)<\/select>/s', $response->getContent(), $propertyMatch);

        $this->assertStringContainsString('selected', $this->extractOptionAttributes($accountMatch[1], 'Personal'));
        $this->assertStringContainsString('selected', $this->extractOptionAttributes($propertyMatch[1], '333'));
    }

    private function extractOptionAttributes(string $selectHtml, string $value): string
    {
        preg_match('/<option[^>]*value="' . preg_quote($value, '/') . '"[^>]*>/', $selectHtml, $match);

        return $match[0] ?? '';
    }

    public function test_not_connected_user_gets_an_error_and_no_properties_flashed(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'example.com', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('domains.ga4-properties.fetch', $domain));

        $response->assertRedirect(route('domains.show', $domain));
        $response->assertSessionMissing('ga4Properties');
        $this->assertSame('error', session('flash')['type']);
    }

    public function test_non_owner_cannot_fetch_properties_for_another_users_domain(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);
        $otherUser = User::factory()->create(['approved_at' => now()]);
        $domain = Domain::query()->create(['domain' => 'beta.example.com', 'user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->post(route('domains.ga4-properties.fetch', $domain))
            ->assertStatus(403);
    }
}
