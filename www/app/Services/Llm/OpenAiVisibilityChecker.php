<?php

declare(strict_types=1);

namespace App\Services\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Bir anahtar kelimeyi dogrudan ChatGPT'ye (OpenAI Chat Completions API)
 * soru olarak sorup cevapta domain'in gecip gecmedigini kontrol eder.
 * DataForSEO'nun AI Optimization API'sinin aksine dogrudan OpenAI'a
 * baglanir - araci komisyonu olmadan yalnizca gercek token maliyeti oder.
 */
final class OpenAiVisibilityChecker
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly Client $client,
        private readonly string $model = 'gpt-4.1-nano',
    ) {
    }

    public function check(string $keyword, string $domain, string $apiKey): LlmVisibilityResult
    {
        $prompt = LlmVisibilityPrompt::userPrompt($keyword);

        try {
            $response = $this->client->post(self::ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => LlmVisibilityPrompt::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => LlmVisibilityPrompt::userPrompt($keyword)],
                    ],
                    'max_tokens' => 400,
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

        $text = $body['choices'][0]['message']['content'] ?? null;
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
            ? sprintf('OpenAI HTTP %d: %s', $status, $message)
            : sprintf('OpenAI HTTP %d', $status);
    }
}
