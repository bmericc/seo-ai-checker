<?php

declare(strict_types=1);

namespace App\Services\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * OpenAiVisibilityChecker'in Google Gemini (generateContent API) karsiligi -
 * bkz. oradaki class yorumu.
 */
final class GeminiVisibilityChecker
{
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private readonly Client $client,
        private readonly string $model = 'gemini-3.1-flash-lite',
    ) {
    }

    public function check(string $keyword, string $domain, string $apiKey): LlmVisibilityResult
    {
        $url = sprintf(self::ENDPOINT_TEMPLATE, $this->model);
        $prompt = LlmVisibilityPrompt::userPrompt($keyword);

        try {
            $response = $this->client->post($url, [
                'query' => ['key' => $apiKey],
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'system_instruction' => ['parts' => [['text' => LlmVisibilityPrompt::SYSTEM_PROMPT]]],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => LlmVisibilityPrompt::userPrompt($keyword)]]],
                    ],
                    'generationConfig' => ['maxOutputTokens' => 400],
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new LlmVisibilityResult(present: false, error: $e->getMessage(), prompt: $prompt);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        if ($status !== 200 || !is_array($body)) {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body), prompt: $prompt);
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || $text === '') {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body), prompt: $prompt);
        }

        return new LlmVisibilityResult(
            present: str_contains(mb_strtolower($text), mb_strtolower($domain)),
            response: $text,
            prompt: $prompt,
        );
    }

    private function describeError(int $status, mixed $body): string
    {
        $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

        return is_string($message) && $message !== ''
            ? sprintf('Gemini HTTP %d: %s', $status, $message)
            : sprintf('Gemini HTTP %d', $status);
    }
}
