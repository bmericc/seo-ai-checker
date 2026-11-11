<?php

namespace Tests\Unit\DataForSeo;

use App\Services\DataForSeo\LlmMentionsChecker;
use PHPUnit\Framework\TestCase;

class LlmMentionsCheckerTest extends TestCase
{
    use FakeDataForSeo;

    public function test_collects_both_platforms(): void
    {
        $client = $this->fakeClient([
            // google: aggregated + search
            $this->taskResponse(['total' => ['platform' => [['key' => 'google', 'mentions' => 120, 'ai_search_volume' => 5400]]]]),
            $this->taskResponse(['items' => [
                ['question' => 'en iyi vize sitesi', 'ai_search_volume' => 900, 'sources' => [['domain' => 'www.example.com']]],
                ['question' => 'vize ücreti', 'ai_search_volume' => null, 'sources' => [['url' => 'https://other.org/x']]],
            ]]),
            // chat_gpt: aggregated basarisiz
            $this->taskResponse(null, 40400, 'No data.'),
        ]);

        $data = (new LlmMentionsChecker($client))->check('example.com');

        $google = $data['platforms']['google'];
        $this->assertSame(120, $google['mentions']);
        $this->assertSame(5400, $google['ai_search_volume']);
        $this->assertNull($google['error']);
        $this->assertTrue($google['top_questions'][0]['cited']);
        $this->assertFalse($google['top_questions'][1]['cited']);
        $this->assertSame(2792, $google['location_code']);

        $chatGpt = $data['platforms']['chat_gpt'];
        $this->assertStringContainsString('No data.', $chatGpt['error']);
        $this->assertSame(2840, $chatGpt['location_code']);
        $this->assertSame('en', $chatGpt['language_code']);

        $first = $this->requestBody(0);
        $this->assertSame([['domain' => 'example.com', 'include_subdomains' => true]], $first[0]['target']);
        $this->assertSame('google', $first[0]['platform']);
        $this->assertSame(10, $this->requestBody(1)[0]['limit']);
        $this->assertSame('chat_gpt', $this->requestBody(2)[0]['platform']);
    }
}
