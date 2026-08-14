<?php

declare(strict_types=1);

namespace App\Services\Llm;

final class LlmVisibilityResult
{
    public function __construct(
        public readonly bool $present,
        public readonly ?string $response = null,
        public readonly ?string $error = null,
        public readonly ?string $prompt = null,
        public readonly ?string $followUpPrompt = null,
        public readonly ?string $followUpResponse = null,
        public readonly bool $followUpPresent = false,
    ) {
    }

    /**
     * @return array{present: bool, response: ?string, error: ?string, prompt: ?string, follow_up_prompt: ?string, follow_up_response: ?string, follow_up_present: bool}
     */
    public function toArray(): array
    {
        return [
            'present' => $this->present,
            'response' => $this->response,
            'error' => $this->error,
            'prompt' => $this->prompt,
            'follow_up_prompt' => $this->followUpPrompt,
            'follow_up_response' => $this->followUpResponse,
            'follow_up_present' => $this->followUpPresent,
        ];
    }
}
