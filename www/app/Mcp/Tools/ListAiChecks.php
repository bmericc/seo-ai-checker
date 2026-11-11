<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\PromptExploration;
use App\Support\AiAssistant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Domain'in yapay zeka kontrol gecmisi: hem API ile (explore_prompt) hem
 * manuel olarak (check_ai_answer) kaydedilen sonuclar, en yeniden eskiye.
 */
#[Name('list_ai_checks')]
#[Description('History of AI visibility checks for a domain: every saved check_ai_answer (manual) and explore_prompt (API) result, newest first, with per-assistant name, model and mention status. Filter by keyword, assistant or prompt text to follow one question over time. Read one entry in full with get_prompt_exploration.')]
#[IsReadOnly]
class ListAiChecks extends SeoTool
{
    private const MAX_LIMIT = 100;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'prompt_contains' => $schema->string()->description('Only entries whose prompt contains this text. Optional.'),
            'keyword' => $schema->string()->description('Only entries checked for this keyword. Optional.'),
            'assistant' => $schema->string()->description('Only entries answered by this assistant (claude, chatgpt, grok…). Optional.'),
            'source' => $schema->string()->enum(['manual', 'api'])->description('Only manual (check_ai_answer) or API (explore_prompt) entries. Optional.'),
            'limit' => $schema->integer()->description('Maximum entries, 1-100. Default 20.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'prompt_contains' => ['nullable', 'string', 'max:200'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'assistant' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'in:manual,api'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        $limit = $data['limit'] ?? 20;
        $assistant = isset($data['assistant']) ? AiAssistant::normalize($data['assistant']) : null;
        $needle = isset($data['keyword']) ? mb_strtolower(trim($data['keyword'])) : null;
        $keywordIds = $needle === null ? null : $domain->keywords()->get()
            ->filter(fn ($k) => mb_strtolower(trim($k->keyword)) === $needle)->pluck('id')->all();
        $postFilter = ($data['source'] ?? null) !== null || $assistant !== null;

        $entries = $domain->promptExplorations()
            ->with('keyword')
            ->when($keywordIds !== null, fn ($q) => $q->whereIn('keyword_id', $keywordIds))
            ->when($data['prompt_contains'] ?? null, fn ($q, string $text) => $q->where('prompt', 'like', '%' . addcslashes($text, '%_\\') . '%'))
            ->limit($postFilter ? self::MAX_LIMIT * 5 : $limit)
            ->get()
            ->map(fn (PromptExploration $exploration) => $this->entry($exploration))
            ->when($data['source'] ?? null, fn ($c, string $source) => $c->filter(fn (array $e) => in_array($source, $e['sources'], true)))
            ->when($assistant, fn ($c, string $a) => $c->filter(fn (array $e) => array_key_exists($a, $e['assistants'])))
            ->take($limit)
            ->values();

        return Response::json([
            'domain' => $domain->domain,
            'count' => $entries->count(),
            'mentioned_count' => $entries->filter(fn (array $e) => $e['mentioned'] > 0)->count(),
            'entries' => $entries->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(PromptExploration $exploration): array
    {
        $assistants = [];
        $sources = [];
        foreach ($exploration->providers ?? [] as $provider) {
            $result = $exploration->resultFor($provider);
            $source = ($result['source'] ?? null) === 'manual' ? 'manual' : 'api';
            $sources[] = $source;
            $assistants[$provider] = array_filter([
                'label' => AiAssistant::label($provider),
                'model' => $result['model'] ?? null,
                'status' => $result === null ? 'pending' : (($result['error'] ?? null) !== null ? 'error' : (($result['present'] ?? false) ? 'mentioned' : 'not_mentioned')),
            ], fn ($value) => $value !== null);
        }

        return [
            'exploration_id' => $exploration->id,
            'prompt' => $exploration->prompt,
            'keyword' => $exploration->keyword?->keyword,
            'checked_at' => self::iso($exploration->created_at),
            'sources' => array_values(array_unique($sources)),
            'assistants' => $assistants,
        ] + $exploration->mentionSummary();
    }
}
