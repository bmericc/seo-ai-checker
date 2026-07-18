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
}
