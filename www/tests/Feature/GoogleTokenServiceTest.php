<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Google\GoogleTokenService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private function serviceWithResponses(array $responses, ?string $clientId = 'id', ?string $clientSecret = 'secret'): GoogleTokenService
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new GoogleTokenService($client, $clientId, $clientSecret);
    }

    public function test_returns_null_when_user_has_no_refresh_token(): void
    {
        $user = User::factory()->create(['google_refresh_token' => null]);

        $service = $this->serviceWithResponses([]);

        $this->assertNull($service->getValidAccessToken($user));
    }

    public function test_returns_stored_access_token_when_not_expired_without_calling_google(): void
    {
        $user = User::factory()->create([
            'google_refresh_token' => 'refresh-token',
            'google_access_token' => 'still-valid-token',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $service = $this->serviceWithResponses([]);

        $this->assertSame('still-valid-token', $service->getValidAccessToken($user));
    }

    public function test_refreshes_and_persists_a_new_token_when_expired(): void
    {
        $user = User::factory()->create([
            'google_refresh_token' => 'refresh-token',
            'google_access_token' => 'expired-token',
            'google_token_expires_at' => now()->subMinute(),
        ]);

        $body = json_encode(['access_token' => 'new-token', 'expires_in' => 3600]);
        $service = $this->serviceWithResponses([new Response(200, [], $body)]);

        $token = $service->getValidAccessToken($user);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $user->fresh()->google_access_token);
        $this->assertTrue($user->fresh()->google_token_expires_at->isFuture());
    }

    public function test_refresh_failure_returns_null(): void
    {
        $user = User::factory()->create([
            'google_refresh_token' => 'refresh-token',
            'google_access_token' => 'expired-token',
            'google_token_expires_at' => now()->subMinute(),
        ]);

        $service = $this->serviceWithResponses([new Response(400, [], json_encode(['error' => 'invalid_grant']))]);

        $this->assertNull($service->getValidAccessToken($user));
    }
}
