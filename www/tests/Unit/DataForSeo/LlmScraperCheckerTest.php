<?php

namespace Tests\Unit\DataForSeo;

use App\Services\DataForSeo\LlmScraperChecker;
use PHPUnit\Framework\TestCase;

class LlmScraperCheckerTest extends TestCase
{
    use FakeDataForSeo;

    public function test_detects_domain_in_answer_and_cleans_citations(): void
    {
        $client = $this->fakeClient([$this->taskResponse([
            'markdown' => "Vize için **example.com** ve resmi konsolosluk sitesine bakın.",
            'sources' => [
                ['url' => 'https://www.example.com/vize?utm_source=chatgpt.com', 'title' => 'Vize'],
                ['url' => 'https://www.example.com/vize?utm_source=chatgpt.com'],
                ['url' => 'javascript:alert(1)'],
                ['url' => 'https://other.org/a?b=1&gclid=x'],
            ],
        ])]);

        $result = (new LlmScraperChecker($client))->check(LlmScraperChecker::ENGINE_CHATGPT, 'yunanistan vizesi', 'example.com');

        $this->assertTrue($result->present);
        $this->assertNull($result->error);
        $this->assertSame('chatgpt.com', $result->model);
        $this->assertSame(['https://www.example.com/vize', 'https://other.org/a?b=1'], $result->citations);

        $body = $this->requestBody();
        $this->assertSame('yunanistan vizesi konusunda hangi web sitelerine bakmalıyım?', $body[0]['keyword']);
        $this->assertStringEndsWith('/v3/ai_optimization/chat_gpt/llm_scraper/live/advanced', (string) $this->requests[0]->getUri());
    }

    public function test_cited_source_counts_as_present_even_without_text_mention(): void
    {
        $client = $this->fakeClient([$this->taskResponse([
            'markdown' => 'Resmi kaynaklara bakabilirsiniz.',
            'sources' => [['url' => 'https://blog.example.com/yazi']],
        ])]);

        $result = (new LlmScraperChecker($client))->check(LlmScraperChecker::ENGINE_GEMINI, 'kelime', 'example.com');

        $this->assertTrue($result->present);
        $this->assertStringEndsWith('/v3/ai_optimization/gemini/llm_scraper/live/advanced', (string) $this->requests[0]->getUri());
    }

    public function test_absent_domain_and_errors(): void
    {
        $client = $this->fakeClient([
            $this->taskResponse(['markdown' => 'Başka siteler: other.org', 'sources' => []]),
            $this->taskResponse(['markdown' => '']),
            $this->taskResponse(null, 40400, 'Not Found.'),
        ]);
        $checker = new LlmScraperChecker($client);

        $this->assertFalse($checker->check(LlmScraperChecker::ENGINE_CHATGPT, 'k', 'example.com')->present);
        $this->assertNotNull($checker->check(LlmScraperChecker::ENGINE_CHATGPT, 'k', 'example.com')->error);

        $failed = $checker->check(LlmScraperChecker::ENGINE_CHATGPT, 'k', 'example.com');
        $this->assertFalse($failed->present);
        $this->assertStringContainsString('Not Found.', $failed->error);
    }

    public function test_escapes_percent_and_plus(): void
    {
        $client = $this->fakeClient([$this->taskResponse(['markdown' => 'x'])]);

        (new LlmScraperChecker($client))->ask(LlmScraperChecker::ENGINE_CHATGPT, '%50 indirim c++', 'example.com');

        $this->assertSame('%2550 indirim c%2B%2B', $this->requestBody()[0]['keyword']);
    }
}
