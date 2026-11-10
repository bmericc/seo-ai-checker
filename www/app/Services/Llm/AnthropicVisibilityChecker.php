<?php

declare(strict_types=1);

namespace App\Services\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * OpenAiVisibilityChecker'in Anthropic (Claude Messages API) karsiligi -
 * bkz. oradaki class yorumu.
 */
final class AnthropicVisibilityChecker
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_TOKENS = 1500;

    public function __construct(
        private readonly Client $client,
        private readonly string $model = 'claude-haiku-5-5',
    ) {
    }

    public function check(string $keyword, string $domain, string $apiKey): LlmVisibilityResult
    {
        $prompt = LlmVisibilityPrompt::userPrompt($keyword);

        try {
            $response = $this->client->post(self::ENDPOINT, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => self::MAX_TOKENS,
                    'thinking' => ['type' => 'disabled'],
                    'system' => LlmVisibilityPrompt::SYSTEM_PROMPT,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
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

        $text = $this->extractText($body);
        if (!is_string($text) || $text === '') {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body), prompt: $prompt, model: $this->model);
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
            model: $this->model,
        );
    }

    /**
     * Prompt Explorer icin: kullanicinin yazdigi prompt'u oldugu gibi (sistem
     * talimati ve ikinci-tur soru olmadan) sorar.
     */
    public function ask(string $prompt, string $domain, string $apiKey): LlmVisibilityResult
    {
        try {
            $response = $this->client->post(self::ENDPOINT, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => self::MAX_TOKENS,
                    'thinking' => ['type' => 'disabled'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new LlmVisibilityResult(present: false, error: $e->getMessage(), prompt: $prompt, model: $this->model);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        $text = $status === 200 ? $this->extractText($body) : null;

        if (!is_string($text) || $text === '') {
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
    private function askFollowUp(string $apiKey, string $firstPrompt, string $firstResponse, string $followUpPrompt): ?string
    {
        try {
            $response = $this->client->post(self::ENDPOINT, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => self::MAX_TOKENS,
                    'thinking' => ['type' => 'disabled'],
                    'system' => LlmVisibilityPrompt::SYSTEM_PROMPT,
                    'messages' => [
                        ['role' => 'user', 'content' => $firstPrompt],
                        ['role' => 'assistant', 'content' => $firstResponse],
                        ['role' => 'user', 'content' => $followUpPrompt],
                    ],
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

        return $this->extractText($body);
    }

    /**
     * Anthropic yanıtı içerisindeki metin bloğunu arar.
     */
    private function extractText(mixed $body): ?string
    {
        if (!is_array($body) || empty($body['content']) || !is_array($body['content'])) {
            return null;
        }

        foreach ($body['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null) && $block['text'] !== '') {
                return $block['text'];
            }
        }

        return null;
    }

    private function describeError(int $status, mixed $body): string
    {
        if (is_array($body)) {
            $message = $body['error']['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return sprintf('Anthropic HTTP %d: %s', $status, $message);
            }

            $stopReason = $body['stop_reason'] ?? null;
            if ($stopReason === 'max_tokens') {
                return 'Anthropic token sınırı aşıldı (stop_reason: max_tokens).';
            }
        }

        return $status === 200
            ? 'Anthropic boş yanıt döndü.'
            : sprintf('Anthropic HTTP %d', $status);
    }
}
