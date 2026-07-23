<?php

declare(strict_types=1);

namespace App\Services\Llm;

final class LlmVisibilityResult
{
    public function __construct(
        public readonly bool $present,
        public readonly ?string $response = null,
        public readonly ?string $error = null,
    ) {
    }

    /**
     * @return array{present: bool, response: ?string, error: ?string}
     */
    public function toArray(): array
    {
        return [
            'present' => $this->present,
            'response' => $this->response,
            'error' => $this->error,
        ];
    }
}
