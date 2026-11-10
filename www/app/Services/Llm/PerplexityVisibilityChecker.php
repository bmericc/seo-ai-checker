<?php

declare(strict_types=1);

namespace App\Services\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * OpenAiVisibilityChecker'in Perplexity (Sonar API) karsiligi. Perplexity
 * her cevapta canli web aramasi yapar ve kaynak URL'lerini ayrica dondurur
 * ("citations" / "search_results"); domain cevap metninde gecmese bile bu
 * kaynaklarda yer aliyorsa gorunur sayilir.
 */
final class PerplexityVisibilityChecker
{
    private const ENDPOINT = 'https://api.perplexity.ai/chat/completions';
    private const MAX_TOKENS = 1500;

    public function __construct(
        private readonly Client $client,
        private readonly string $model = 'sonar',
    ) {
    }

    public function check(string $keyword, string $domain, string $apiKey): LlmVisibilityResult
    {
        $prompt = LlmVisibilityPrompt::userPrompt($keyword);
        $first = $this->request($apiKey, [
            ['role' => 'system', 'content' => LlmVisibilityPrompt::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
        ]);

        if ($first['error'] !== null) {
            return new LlmVisibilityResult(present: false, error: $first['error'], prompt: $prompt, model: $this->model);
        }

        $followUpPrompt = LlmVisibilityPrompt::FOLLOW_UP_PROMPT;
        $followUp = $this->request($apiKey, [
            ['role' => 'system', 'content' => LlmVisibilityPrompt::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $prompt],
            ['role' => 'assistant', 'content' => $first['text']],
            ['role' => 'user', 'content' => $followUpPrompt],
        ]);
        $followUpText = $followUp['error'] === null ? $followUp['text'] : null;

        return new LlmVisibilityResult(
            present: $this->isPresent($first['text'], $first['citations'], $domain),
            response: $first['text'],
            prompt: $prompt,
            followUpPrompt: $followUpPrompt,
            followUpResponse: $followUpText,
            followUpPresent: $followUpText !== null && $this->isPresent($followUpText, $followUp['citations'], $domain),
            model: $this->model,
            citations: $first['citations'],
        );
    }

    public function ask(string $prompt, string $domain, string $apiKey): LlmVisibilityResult
    {
        $result = $this->request($apiKey, [['role' => 'user', 'content' => $prompt]]);

        if ($result['error'] !== null) {
            return new LlmVisibilityResult(present: false, error: $result['error'], prompt: $prompt, model: $this->model);
        }

        return new LlmVisibilityResult(
            present: $this->isPresent($result['text'], $result['citations'], $domain),
            response: $result['text'],
            prompt: $prompt,
            model: $this->model,
            citations: $result['citations'],
        );
    }

    /**
     * @param  list<string>  $citations
     */
    private function isPresent(string $text, array $citations, string $domain): bool
    {
        if (LlmMentions::mentions($text, $domain)) {
            return true;
        }

        $domain = LlmMentions::normalizeHost($domain);
        foreach ($citations as $url) {
            $host = LlmMentions::normalizeHost((string) parse_url($url, PHP_URL_HOST));
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, citations: list<string>, error: ?string}
     */
    private function request(string $apiKey, array $messages): array
    {
        try {
            $response = $this->client->post(self::ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => self::MAX_TOKENS,
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return ['text' => '', 'citations' => [], 'error' => $e->getMessage()];
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        $text = is_array($body) ? ($body['choices'][0]['message']['content'] ?? null) : null;

        if ($status !== 200 || !is_string($text) || $text === '') {
            $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

            return [
                'text' => '',
                'citations' => [],
                'error' => is_string($message) && $message !== ''
                    ? sprintf('Perplexity HTTP %d: %s', $status, $message)
                    : ($status === 200 ? 'Perplexity boş yanıt döndü.' : sprintf('Perplexity HTTP %d', $status)),
            ];
        }

        return ['text' => $text, 'citations' => $this->extractCitations($body), 'error' => null];
    }

    /**
     * @return list<string>
     */
    private function extractCitations(array $body): array
    {
        $urls = [];

        foreach ($body['citations'] ?? [] as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        foreach ($body['search_results'] ?? [] as $result) {
            $url = is_array($result) ? ($result['url'] ?? null) : null;
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }
}
