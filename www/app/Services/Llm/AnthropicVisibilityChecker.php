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

    private const SYSTEM_PROMPT = 'Bir kullanıcı bir konuda arama yapıyor. Ona yardımcı olacak, '
        . 'güncel ve güvenilir bilgi veren web sitelerini de belirterek kısa ve öz bir cevap ver.';

    public function __construct(
        private readonly Client $client,
        private readonly string $model = 'claude-haiku-4-5-20251001',
    ) {
    }

    public function check(string $keyword, string $domain, string $apiKey): LlmVisibilityResult
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
                    'max_tokens' => 400,
                    'system' => self::SYSTEM_PROMPT,
                    'messages' => [
                        ['role' => 'user', 'content' => $keyword],
                    ],
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new LlmVisibilityResult(present: false, error: $e->getMessage());
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        if ($status !== 200 || !is_array($body)) {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body));
        }

        $text = $body['content'][0]['text'] ?? null;
        if (!is_string($text) || $text === '') {
            return new LlmVisibilityResult(present: false, error: $this->describeError($status, $body));
        }

        return new LlmVisibilityResult(
            present: str_contains(mb_strtolower($text), mb_strtolower($domain)),
            response: $text,
        );
    }

    private function describeError(int $status, mixed $body): string
    {
        $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

        return is_string($message) && $message !== ''
            ? sprintf('Anthropic HTTP %d: %s', $status, $message)
            : sprintf('Anthropic HTTP %d', $status);
    }
}
