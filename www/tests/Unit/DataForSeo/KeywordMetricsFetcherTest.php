<?php

namespace Tests\Unit\DataForSeo;

use App\Services\DataForSeo\DataForSeoException;
use App\Services\DataForSeo\KeywordMetricsFetcher;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class KeywordMetricsFetcherTest extends TestCase
{
    use FakeDataForSeo;

    public function test_parses_keyword_overview_items(): void
    {
        $client = $this->fakeClient([$this->taskResponse(['items' => [[
            'keyword' => 'yunanistan vizesi',
            'keyword_info' => [
                'search_volume' => 12100,
                'cpc' => 0.4567,
                'competition' => 0.31,
                'monthly_searches' => [
                    ['year' => 2026, 'month' => 10, 'search_volume' => 14800],
                    ['year' => 2026, 'month' => 9, 'search_volume' => 9900],
                ],
            ],
            'keyword_properties' => ['keyword_difficulty' => 42],
            'search_intent_info' => ['main_intent' => 'informational'],
        ]]])]);

        $metrics = (new KeywordMetricsFetcher($client))->fetch(['Yunanistan Vizesi ', 'yunanistan vizesi']);

        $this->assertCount(1, $metrics);
        $m = $metrics['yunanistan vizesi'];
        $this->assertSame(12100, $m->searchVolume);
        $this->assertSame(0.46, $m->cpc);
        $this->assertSame(0.31, $m->competition);
        $this->assertSame(42, $m->keywordDifficulty);
        $this->assertSame('informational', $m->searchIntent);
        $this->assertSame([2026, 9], [$m->monthlySearches[0]['year'], $m->monthlySearches[0]['month']]);

        $body = $this->requestBody();
        $this->assertSame(['yunanistan vizesi'], $body[0]['keywords']);
        $this->assertSame(2792, $body[0]['location_code']);
        $this->assertSame('tr', $body[0]['language_code']);
        $this->assertStringEndsWith('/v3/dataforseo_labs/google/keyword_overview/live', (string) $this->requests[0]->getUri());
        $this->assertSame('Basic ' . base64_encode('login:password'), $this->requests[0]->getHeaderLine('Authorization'));
    }

    public function test_items_without_metrics_yield_nulls(): void
    {
        $client = $this->fakeClient([$this->taskResponse(['items' => [['keyword' => 'nadir kelime']]])]);

        $m = (new KeywordMetricsFetcher($client))->fetch(['nadir kelime'])['nadir kelime'];

        $this->assertNull($m->searchVolume);
        $this->assertNull($m->keywordDifficulty);
        $this->assertSame([], $m->monthlySearches);
    }

    public function test_failed_task_throws(): void
    {
        $client = $this->fakeClient([$this->taskResponse(null, 40501, 'Invalid Field')]);

        $this->expectException(DataForSeoException::class);
        $this->expectExceptionMessage('Invalid Field');

        (new KeywordMetricsFetcher($client))->fetch(['x']);
    }

    public function test_top_level_error_throws(): void
    {
        $client = $this->fakeClient([new Response(200, [], json_encode(['status_code' => 40200, 'status_message' => 'Payment Required.']))]);

        $this->expectException(DataForSeoException::class);
        $this->expectExceptionMessage('Payment Required.');

        (new KeywordMetricsFetcher($client))->fetch(['x']);
    }

    public function test_not_configured_makes_no_request(): void
    {
        $client = $this->fakeClient([], null, null);
        $fetcher = new KeywordMetricsFetcher($client);

        $this->assertFalse($fetcher->isConfigured());
        $this->expectException(DataForSeoException::class);
        $fetcher->fetch(['x']);
    }
}
