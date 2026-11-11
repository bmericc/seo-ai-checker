<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\PromptExploration;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_prompt_exploration')]
#[Description('Returns one saved AI check (from explore_prompt, check_ai_answer or list_ai_checks): per assistant whether the domain was mentioned, cited sources and the answer text, plus other sites the assistants recommended.')]
#[IsReadOnly]
class GetPromptExploration extends SeoTool
{
    private const MAX_ANSWER_CHARS = 4000;

    public function schema(JsonSchema $schema): array
    {
        return [
            'exploration_id' => $schema->integer()->description('Id returned by explore_prompt, check_ai_answer or list_ai_checks.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $request->validate(['exploration_id' => ['required', 'integer']]);

        $exploration = PromptExploration::with('domain')->find((int) $request->get('exploration_id'));
        if ($exploration === null || !$exploration->domain?->isVisibleTo($user)) {
            throw new ToolError(__('Kayıt bulunamadı.'));
        }

        $answers = [];
        foreach ($exploration->providers ?? [] as $provider) {
            $result = $exploration->resultFor($provider);
            $answers[$provider] = $result === null ? ['status' => 'pending'] : array_filter([
                'status' => ($result['error'] ?? null) === null ? 'answered' : 'error',
                'mentions_domain' => $result['present'] ?? null,
                'assistant_label' => \App\Support\AiAssistant::label($provider),
                'model' => $result['model'] ?? null,
                'source' => ($result['source'] ?? null) === 'manual' ? 'manual' : 'api',
                'error' => $result['error'] ?? null,
                'citations' => $result['citations'] ?? null,
                'answer' => isset($result['response']) ? Str::limit((string) $result['response'], self::MAX_ANSWER_CHARS) : null,
            ], fn ($value) => $value !== null);
        }

        return Response::json([
            'domain' => $exploration->domain->domain,
            'prompt' => $exploration->prompt,
            'complete' => $exploration->isComplete(),
            'summary' => $exploration->mentionSummary(),
            'other_sites_mentioned' => $exploration->mentionedSites(),
            'answers' => $answers,
            'url' => route('domains.prompts.show', [$exploration->domain, $exploration]),
        ]);
    }
}
