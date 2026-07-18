<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Bing\BingTokenService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BingTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private function serviceWithResponses(array $responses, ?string $clientId = 'id', ?string $clientSecret = 'secret'): BingTokenService
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new BingTokenService($client, $clientId, $clientSecret);
    }

    public function test_returns_null_when_user_has_no_refresh_token(): void
    {
        $user = User::factory()->create(['bing_refresh_token' => null]);

        $service = $this->serviceWithResponses([]);

        $this->assertNull($service->getValidAccessToken($user));
    }

    public function test_returns_stored_access_token_when_not_expired_without_calling_bing(): void
    {
        $user = User::factory()->create([
            'bing_refresh_token' => 'refresh-token',
            'bing_access_token' => 'still-valid-token',
            'bing_token_expires_at' => now()->addHour(),
        ]);

        $service = $this->serviceWithResponses([]);

        $this->assertSame('still-valid-token', $service->getValidAccessToken($user));
    }

    public function test_refreshes_and_persists_a_new_token_when_expired(): void
    {
        $user = User::factory()->create([
            'bing_refresh_token' => 'refresh-token',
            'bing_access_token' => 'expired-token',
            'bing_token_expires_at' => now()->subMinute(),
        ]);

        $body = json_encode(['access_token' => 'new-token', 'expires_in' => 3600]);
        $service = $this->serviceWithResponses([new Response(200, [], $body)]);

        $token = $service->getValidAccessToken($user);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $user->fresh()->bing_access_token);
        $this->assertTrue($user->fresh()->bing_token_expires_at->isFuture());
    }

    public function test_refresh_failure_returns_null(): void
    {
        $user = User::factory()->create([
            'bing_refresh_token' => 'refresh-token',
            'bing_access_token' => 'expired-token',
            'bing_token_expires_at' => now()->subMinute(),
        ]);

        $service = $this->serviceWithResponses([new Response(400, [], json_encode(['error' => 'invalid_grant']))]);

        $this->assertNull($service->getValidAccessToken($user));
    }
}
