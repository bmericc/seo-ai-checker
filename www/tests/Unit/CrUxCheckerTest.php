<?php

namespace Tests\Unit;

use App\Services\Crux\CrUxChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CrUxCheckerTest extends TestCase
{
    private function checkerWithResponses(array $responses, ?string $apiKey = 'test-key'): CrUxChecker
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new CrUxChecker($client, $apiKey);
    }

    public function test_missing_api_key_skips_the_request_entirely(): void
    {
        $checker = $this->checkerWithResponses([], apiKey: null);

        $result = $checker->check('https://example.com');

        $this->assertFalse($result->configured);
        $this->assertFalse($result->found);
    }

    public function test_parses_metrics_and_rates_them(): void
    {
        $body = json_encode([
            'record' => [
                'metrics' => [
                    'largest_contentful_paint' => ['percentiles' => ['p75' => 2000]],
                    'cumulative_layout_shift' => ['percentiles' => ['p75' => 0.3]],
                    'interaction_to_next_paint' => ['percentiles' => ['p75' => 300]],
                ],
                'collectionPeriod' => [
                    'firstDate' => ['year' => 2026, 'month' => 6, 'day' => 19],
                    'lastDate' => ['year' => 2026, 'month' => 7, 'day' => 17],
                ],
            ],
        ]);

        $checker = $this->checkerWithResponses([new Response(200, [], $body)]);

        $result = $checker->check('https://example.com');

        $this->assertTrue($result->configured);
        $this->assertTrue($result->found);
        $this->assertSame('good', $result->metrics['largest_contentful_paint']['rating']);
        $this->assertSame('poor', $result->metrics['cumulative_layout_shift']['rating']);
        $this->assertSame('needs_improvement', $result->metrics['interaction_to_next_paint']['rating']);
        $this->assertSame('2026-06-19 — 2026-07-17', $result->collectionPeriod);
    }

    public function test_404_means_no_data_found_but_still_configured(): void
    {
        $checker = $this->checkerWithResponses([new Response(404, [], '{}')]);

        $result = $checker->check('https://example.com');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->found);
        $this->assertNull($result->error);
    }

    public function test_unexpected_status_is_reported_as_an_error(): void
    {
        $checker = $this->checkerWithResponses([new Response(500, [], '{}')]);

        $result = $checker->check('https://example.com');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->found);
        $this->assertSame('HTTP 500', $result->error);
    }

    public function test_permission_denied_surfaces_the_google_error_reason(): void
    {
        $body = json_encode([
            'error' => [
                'code' => 403,
                'message' => 'Requests to this API chromeuxreport.googleapis.com method google.chrome.uxreport.v1.ChromeUXReport.QueryRecord are blocked.',
                'status' => 'PERMISSION_DENIED',
                'details' => [
                    ['reason' => 'API_KEY_SERVICE_BLOCKED'],
                ],
            ],
        ]);

        $checker = $this->checkerWithResponses([new Response(403, [], $body)]);

        $result = $checker->check('https://example.com');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->found);
        $this->assertStringContainsString('API_KEY_SERVICE_BLOCKED', $result->error);
        $this->assertStringContainsString('blocked', $result->error);
    }

    public function test_network_failure_is_reported_as_an_error(): void
    {
        $checker = $this->checkerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('POST', 'https://chromeuxreport.googleapis.com/')),
        ]);

        $result = $checker->check('https://example.com');

        $this->assertTrue($result->configured);
        $this->assertFalse($result->found);
        $this->assertNotNull($result->error);
    }
}
