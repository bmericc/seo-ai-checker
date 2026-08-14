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
                        ['role' => 'user', 'content' => $prompt],
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

        $followUpPrompt = LlmVisibilityPrompt::FOLLOW_UP_PROMPT;
        $followUpResponse = $this->askFollowUp($apiKey, $prompt, $text, $followUpPrompt);

        return new LlmVisibilityResult(
            present: str_contains(mb_strtolower($text), mb_strtolower($domain)),
            response: $text,
            prompt: $prompt,
            followUpPrompt: $followUpPrompt,
            followUpResponse: $followUpResponse,
            followUpPresent: $followUpResponse !== null && str_contains(mb_strtolower($followUpResponse), mb_strtolower($domain)),
        );
    }

    /**
     * Ilk cevabi konusma gecmisine ekleyip ikinci-tur soruyu sorar - basarisiz
     * olursa (agdan/API'den) null doner, bu ana sonucu bozmaz (bkz. check()).
     */
    private function askFollowUp(string $apiKey, string $firstPrompt, string $firstResponse, string $followUpPrompt): ?string
    {
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
                        ['role' => 'user', 'content' => $firstPrompt],
                        ['role' => 'assistant', 'content' => $firstResponse],
                        ['role' => 'user', 'content' => $followUpPrompt],
                    ],
                    'max_tokens' => 400,
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $text = is_array($body) ? ($body['choices'][0]['message']['content'] ?? null) : null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    private function describeError(int $status, mixed $body): string
    {
        $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

        return is_string($message) && $message !== ''
            ? sprintf('OpenAI HTTP %d: %s', $status, $message)
            : sprintf('OpenAI HTTP %d', $status);
    }
}
