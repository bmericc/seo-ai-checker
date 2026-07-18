<?php

namespace Tests\Unit;

use App\Services\Ga4\Ga4Checker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class Ga4CheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses): Ga4Checker
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new Ga4Checker($client);
    }

    public function test_missing_property_id_skips_the_request_entirely(): void
    {
        $checker = $this->checkerWithResponses([]);

        $result = $checker->check(null, 'token');

        $this->assertFalse($result->configured);
    }

    public function test_missing_access_token_skips_the_request_entirely(): void
    {
        $checker = $this->checkerWithResponses([]);

        $result = $checker->check('123456789', null);

        $this->assertFalse($result->configured);
    }

    public function test_sums_sessions_and_isolates_organic_channel(): void
    {
        $body = json_encode(['rows' => [
            [
                'dimensionValues' => [['value' => 'Organic Search']],
                'metricValues' => [['value' => '80'], ['value' => '60']],
            ],
            [
                'dimensionValues' => [['value' => 'Direct']],
                'metricValues' => [['value' => '40'], ['value' => '30']],
            ],
        ]]);

        $checker = $this->checkerWithResponses([new Response(200, [], $body)]);

        $result = $checker->check('123456789', 'token');

        $this->assertTrue($result->configured);
        $this->assertSame('123456789', $result->propertyId);
        $this->assertSame(120.0, $result->totalSessions);
        $this->assertSame(80.0, $result->organicSessions);
        $this->assertSame(90.0, $result->activeUsers);
    }

    public function test_http_error_is_reported(): void
    {
        $body = json_encode(['error' => ['message' => 'User does not have sufficient permissions for this property.']]);

        $checker = $this->checkerWithResponses([new Response(403, [], $body)]);

        $result = $checker->check('123456789', 'token');

        $this->assertTrue($result->configured);
        $this->assertStringContainsString('sufficient permissions', $result->error);
    }

    public function test_network_failure_is_reported_as_an_error(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('POST', self::class)),
        ]);

        $result = $checker->check('123456789', 'token');

        $this->assertTrue($result->configured);
        $this->assertNotNull($result->error);
    }
}
