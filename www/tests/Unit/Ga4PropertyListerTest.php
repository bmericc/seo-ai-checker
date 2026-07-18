<?php

namespace Tests\Unit;

use App\Services\Ga4\Ga4PropertyLister;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class Ga4PropertyListerTest extends TestCase
{
    private function listerWithResponses(array $responses): Ga4PropertyLister
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new Ga4PropertyLister($client);
    }

    public function test_missing_access_token_skips_the_request_entirely(): void
    {
        $lister = $this->listerWithResponses([]);

        $result = $lister->list(null);

        $this->assertFalse($result->configured);
        $this->assertSame([], $result->properties);
    }

    public function test_parses_properties_across_multiple_accounts(): void
    {
        $body = json_encode([
            'accountSummaries' => [
                [
                    'displayName' => 'Acme Inc',
                    'propertySummaries' => [
                        ['property' => 'properties/111', 'displayName' => 'acme.com'],
                        ['property' => 'properties/222', 'displayName' => 'shop.acme.com'],
                    ],
                ],
                [
                    'displayName' => 'Personal',
                    'propertySummaries' => [
                        ['property' => 'properties/333', 'displayName' => 'blog.example.com'],
                    ],
                ],
            ],
        ]);

        $lister = $this->listerWithResponses([new Response(200, [], $body)]);

        $result = $lister->list('token');

        $this->assertTrue($result->configured);
        $this->assertCount(3, $result->properties);
        $this->assertSame('111', $result->properties[0]->propertyId);
        $this->assertSame('acme.com', $result->properties[0]->label);
        $this->assertSame('Acme Inc', $result->properties[0]->accountName);
        $this->assertSame('333', $result->properties[2]->propertyId);
        $this->assertSame('blog.example.com', $result->properties[2]->label);
        $this->assertSame('Personal', $result->properties[2]->accountName);
    }

    public function test_follows_next_page_token_to_collect_properties_across_pages(): void
    {
        $firstBody = json_encode([
            'accountSummaries' => [
                [
                    'displayName' => 'Acme Inc',
                    'propertySummaries' => [
                        ['property' => 'properties/111', 'displayName' => 'acme.com'],
                    ],
                ],
            ],
            'nextPageToken' => 'page-2',
        ]);
        $secondBody = json_encode([
            'accountSummaries' => [
                [
                    'displayName' => 'Beta LLC',
                    'propertySummaries' => [
                        ['property' => 'properties/222', 'displayName' => 'beta.com'],
                    ],
                ],
            ],
        ]);

        $lister = $this->listerWithResponses([
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
        ]);

        $result = $lister->list('token');

        $this->assertTrue($result->configured);
        $this->assertCount(2, $result->properties);
        $this->assertSame('111', $result->properties[0]->propertyId);
        $this->assertSame('222', $result->properties[1]->propertyId);
    }

    public function test_no_accounts_returns_an_empty_property_list(): void
    {
        $lister = $this->listerWithResponses([new Response(200, [], json_encode(['accountSummaries' => []]))]);

        $result = $lister->list('token');

        $this->assertTrue($result->configured);
        $this->assertSame([], $result->properties);
        $this->assertNull($result->error);
    }

    public function test_http_error_is_reported(): void
    {
        $body = json_encode(['error' => ['message' => 'Invalid Credentials']]);

        $lister = $this->listerWithResponses([new Response(401, [], $body)]);

        $result = $lister->list('token');

        $this->assertTrue($result->configured);
        $this->assertStringContainsString('Invalid Credentials', $result->error);
    }

    public function test_network_failure_is_reported_as_an_error(): void
    {
        $lister = $this->listerWithResponses([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', self::class)),
        ]);

        $result = $lister->list('token');

        $this->assertTrue($result->configured);
        $this->assertNotNull($result->error);
    }
}
