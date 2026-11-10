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
    private const MAX_OUTPUT_TOKENS = 1500;

    public function __construct(
        private readonly Client $client,
        private readonly string $model = 'gemini-3.8-flash',
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
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => ['maxOutputTokens' => self::MAX_OUTPUT_TOKENS],
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new LlmVisibilityResult(present: false, error: $e->getMessage(), prompt: $prompt, model: $this->model);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        if ($status !== 200 || !is_array($body)) {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body), prompt: $prompt, model: $this->model);
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text) || $text === '') {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body), prompt: $prompt, model: $this->model);
        }

        $followUpPrompt = LlmVisibilityPrompt::FOLLOW_UP_PROMPT;
        $followUpResponse = $this->askFollowUp($url, $apiKey, $prompt, $text, $followUpPrompt);

        return new LlmVisibilityResult(
            present: str_contains(mb_strtolower($text), mb_strtolower($domain)),
            response: $text,
            prompt: $prompt,
            followUpPrompt: $followUpPrompt,
            followUpResponse: $followUpResponse,
            followUpPresent: $followUpResponse !== null && str_contains(mb_strtolower($followUpResponse), mb_strtolower($domain)),
            model: $this->model,
        );
    }

    /**
     * Prompt Explorer icin: kullanicinin yazdigi prompt'u oldugu gibi (sistem
     * talimati ve ikinci-tur soru olmadan) sorar.
     */
    public function ask(string $prompt, string $domain, string $apiKey): LlmVisibilityResult
    {
        $url = sprintf(self::ENDPOINT_TEMPLATE, $this->model);

        try {
            $response = $this->client->post($url, [
                'query' => ['key' => $apiKey],
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => ['maxOutputTokens' => self::MAX_OUTPUT_TOKENS],
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new LlmVisibilityResult(present: false, error: $e->getMessage(), prompt: $prompt, model: $this->model);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        $text = is_array($body) ? ($body['candidates'][0]['content']['parts'][0]['text'] ?? null) : null;

        if ($status !== 200 || !is_string($text) || $text === '') {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body), prompt: $prompt, model: $this->model);
        }

        return new LlmVisibilityResult(
            present: LlmMentions::mentions($text, $domain),
            response: $text,
            prompt: $prompt,
            model: $this->model,
        );
    }

    /**
     * Ilk cevabi konusma gecmisine ekleyip ikinci-tur soruyu sorar - basarisiz
     * olursa (agdan/API'den) null doner, bu ana sonucu bozmaz (bkz. check()).
     */
    private function askFollowUp(string $url, string $apiKey, string $firstPrompt, string $firstResponse, string $followUpPrompt): ?string
    {
        try {
            $response = $this->client->post($url, [
                'query' => ['key' => $apiKey],
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'system_instruction' => ['parts' => [['text' => LlmVisibilityPrompt::SYSTEM_PROMPT]]],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $firstPrompt]]],
                        ['role' => 'model', 'parts' => [['text' => $firstResponse]]],
                        ['role' => 'user', 'parts' => [['text' => $followUpPrompt]]],
                    ],
                    'generationConfig' => ['maxOutputTokens' => self::MAX_OUTPUT_TOKENS],
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
        $text = is_array($body) ? ($body['candidates'][0]['content']['parts'][0]['text'] ?? null) : null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    private function describeError(int $status, mixed $body): string
    {
        if (is_array($body)) {
            $message = $body['error']['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return sprintf('Gemini HTTP %d: %s', $status, $message);
            }

            $finishReason = $body['candidates'][0]['finishReason'] ?? null;
            if ($finishReason === 'MAX_TOKENS') {
                return 'Gemini token sınırı aşıldı (finishReason: MAX_TOKENS).';
            }
        }

        return $status === 200
            ? 'Gemini boş yanıt döndü.'
            : sprintf('Gemini HTTP %d', $status);
    }
}
