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
        public readonly ?string $model = null,
        /** @var list<string> Perplexity gibi kaynak gosteren saglayicilarin alintiladigi URL'ler. */
        public readonly array $citations = [],
    ) {
    }

    /**
     * @return array{present: bool, response: ?string, error: ?string, prompt: ?string, follow_up_prompt: ?string, follow_up_response: ?string, follow_up_present: bool, model: ?string}
     */
    public function toArray(): array
    {
        $data = [
            'present' => $this->present,
            'response' => $this->response,
            'error' => $this->error,
            'prompt' => $this->prompt,
            'follow_up_prompt' => $this->followUpPrompt,
            'follow_up_response' => $this->followUpResponse,
            'follow_up_present' => $this->followUpPresent,
            'model' => $this->model,
        ];

        if ($this->citations !== []) {
            $data['citations'] = $this->citations;
        }

        return $data;
    }
}
