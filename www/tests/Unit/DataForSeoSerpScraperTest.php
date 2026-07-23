<?php

namespace Tests\Unit;

use App\Services\Serp\DataForSeoSerpScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class DataForSeoSerpScraperTest extends TestCase
{
    private function scraperWithResponses(array $responses, ?RequestInterface &$capturedRequest = null): DataForSeoSerpScraper
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
        }));
        $client = new Client(['handler' => $stack]);

        return new DataForSeoSerpScraper($client, 'login', 'password');
    }

    private function envelope(array $result, int $topStatus = 20000, int $taskStatus = 20000): array
    {
        return [
            'status_code' => $topStatus,
            'status_message' => 'Ok.',
            'tasks' => [
                [
                    'status_code' => $taskStatus,
                    'status_message' => 'Ok.',
                    'result' => [
                        ['items' => $result],
                    ],
                ],
            ],
        ];
    }

    public function test_sends_basic_auth_header_and_expected_task_payload(): void
    {
        $capturedRequest = null;
        $scraper = $this->scraperWithResponses(
            [new Response(200, [], json_encode($this->envelope([])))],
            $capturedRequest,
        );

        $scraper->search('örnek kelime');

        $this->assertSame('Basic ' . base64_encode('login:password'), $capturedRequest->getHeaderLine('Authorization'));

        $body = json_decode((string) $capturedRequest->getBody(), true);
        $this->assertSame('örnek kelime', $body[0]['keyword']);
        $this->assertSame(2792, $body[0]['location_code']);
        $this->assertSame('tr', $body[0]['language_code']);
    }

    public function test_parses_organic_results_sorted_by_position(): void
    {
        $items = [
            ['type' => 'organic', 'rank_absolute' => 3, 'domain' => 'third.com', 'url' => 'https://third.com/', 'title' => 'Third'],
            ['type' => 'organic', 'rank_absolute' => 1, 'domain' => 'first.com', 'url' => 'https://first.com/', 'title' => 'First'],
        ];
        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($this->envelope($items)))]);

        $result = $scraper->search('kelime');

        $this->assertFalse($result->blocked);
        $this->assertCount(2, $result->organicResults);
        $this->assertSame(1, $result->organicResults[0]->position);
        $this->assertSame('first.com', $result->organicResults[0]->domain);
        $this->assertSame(3, $result->organicResults[1]->position);
    }

    public function test_ignores_non_organic_items_when_building_organic_results(): void
    {
        $items = [
            ['type' => 'featured_snippet', 'domain' => 'snippet.com'],
            ['type' => 'organic', 'rank_absolute' => 1, 'domain' => 'real.com', 'url' => 'https://real.com/', 'title' => 'Real'],
        ];
        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($this->envelope($items)))]);

        $result = $scraper->search('kelime');

        $this->assertCount(1, $result->organicResults);
        $this->assertSame('real.com', $result->organicResults[0]->domain);
    }

    public function test_ai_overview_with_references_reports_cited_domains(): void
    {
        $items = [
            [
                'type' => 'ai_overview',
                'references' => [
                    ['url' => 'https://cited-one.com/page'],
                    ['url' => 'https://cited-two.com/page'],
                ],
            ],
        ];
        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($this->envelope($items)))]);

        $result = $scraper->search('kelime');

        $this->assertTrue($result->aiOverview->present);
        $this->assertTrue($result->aiOverview->citesDomain('cited-one.com'));
        $this->assertTrue($result->aiOverview->citesDomain('cited-two.com'));
        $this->assertNull($result->aiOverview->note);
    }

    public function test_ai_overview_without_extractable_references_still_reports_presence(): void
    {
        $items = [
            ['type' => 'ai_overview'],
        ];
        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($this->envelope($items)))]);

        $result = $scraper->search('kelime');

        $this->assertTrue($result->aiOverview->present);
        $this->assertSame([], $result->aiOverview->citedDomains);
        $this->assertNotNull($result->aiOverview->note);
    }

    public function test_no_ai_overview_item_means_not_present(): void
    {
        $items = [
            ['type' => 'organic', 'rank_absolute' => 1, 'domain' => 'a.com', 'url' => 'https://a.com/', 'title' => 'A'],
        ];
        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($this->envelope($items)))]);

        $result = $scraper->search('kelime');

        $this->assertFalse($result->aiOverview->present);
    }

    public function test_non_200_http_status_is_reported_as_blocked(): void
    {
        $scraper = $this->scraperWithResponses([new Response(500, [], 'Internal Server Error')]);

        $result = $scraper->search('kelime');

        $this->assertTrue($result->blocked);
        $this->assertStringContainsString('HTTP 500', $result->blockReason);
    }

    public function test_top_level_error_status_is_reported_as_blocked(): void
    {
        $body = ['status_code' => 40100, 'status_message' => 'Invalid Login/Password.'];
        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($body))]);

        $result = $scraper->search('kelime');

        $this->assertTrue($result->blocked);
        $this->assertStringContainsString('Invalid Login/Password', $result->blockReason);
    }

    public function test_task_level_error_status_is_reported_as_blocked(): void
    {
        $body = $this->envelope([], topStatus: 20000, taskStatus: 40501);
        $body['tasks'][0]['status_message'] = 'Invalid Field.';

        $scraper = $this->scraperWithResponses([new Response(200, [], json_encode($body))]);

        $result = $scraper->search('kelime');

        $this->assertTrue($result->blocked);
        $this->assertStringContainsString('Invalid Field', $result->blockReason);
    }
}
