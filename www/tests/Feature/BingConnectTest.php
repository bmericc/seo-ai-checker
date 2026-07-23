<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BingConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_start_the_connect_flow(): void
    {
        $this->get(route('bing.connect'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_authenticated_user_starting_connect_is_redirected_to_bing(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $response = $this->actingAs($user)->get(route('bing.connect'));

        $response->assertRedirect();
        $this->assertStringContainsString('bing.com/webmasters/oauth/authorize', $response->headers->get('Location'));
    }

    public function test_callback_without_a_matching_state_is_rejected(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $response = $this->actingAs($user)->get(route('bing.callback', ['code' => 'abc', 'state' => 'wrong']));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('error', session('flash')['type']);
        $this->assertNull($user->fresh()->bing_refresh_token);
    }

    public function test_callback_with_an_error_query_param_is_rejected(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        $response = $this->actingAs($user)->get(route('bing.callback', ['error' => 'access_denied']));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('error', session('flash')['type']);
    }

    public function test_callback_with_a_matching_state_and_valid_code_saves_tokens(): void
    {
        $user = User::factory()->create(['approved_at' => now()]);

        // Prime the expected session state the way connect() would.
        $this->actingAs($user)->get(route('bing.connect'));

        $body = json_encode(['access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600]);
        $mock = new MockHandler([new Response(200, [], $body)]);
        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create($mock)]));

        $state = session('bing_oauth_state');
        $response = $this->get(route('bing.callback', ['code' => 'auth-code', 'state' => $state]));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('success', session('flash')['type']);
        $this->assertSame('access-token', $user->fresh()->bing_access_token);
        $this->assertSame('refresh-token', $user->fresh()->bing_refresh_token);
    }

    public function test_dashboard_shows_the_connect_banner_when_bing_is_never_connected(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'bing_refresh_token' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('Kendi doğruladığınız sitelerin backlink verisini görebilmek için Bing hesabınızı bağlamanız gerekiyor.');
        $response->assertDontSee('Bing hesabınız bağlı.');
    }

    /**
     * Bing'in refresh token akisi kendi tarafinda arizali oldugundan
     * (bkz. BingTokenService), bir kullanicinin bing_refresh_token'i olmasi
     * onun HALA calistigi anlamina gelmez - bu yuzden "yeniden baglan"
     * linki, refresh_token zaten var olsa da her zaman erisilebilir
     * kalmali, alarm niteliginde olmayan bir bicimde.
     */
    public function test_dashboard_shows_a_low_key_reconnect_link_when_bing_is_already_connected(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'bing_refresh_token' => 'refresh-token']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('Bing hesabınız bağlı.');
        $response->assertSee('Sorun yaşıyorsanız yeniden bağlayın');
        $response->assertDontSee('Kendi doğruladığınız sitelerin backlink verisini görebilmek için Bing hesabınızı bağlamanız gerekiyor.');
    }
}
