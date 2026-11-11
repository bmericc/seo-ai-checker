<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Keyword;
use App\Services\Llm\LlmVisibilityPrompt;
use App\Support\AiAssistant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Otomatik manuel kontrol icin is listesi: domain'in takip edilen
 * kelimeleri ve her biri icin API kontrollerinin sordugu standart soru.
 */
#[Name('get_ai_check_prompts')]
#[Description('Lists the keywords a domain tracks, each with the standard question to answer for a manual AI visibility check (the same question the API checks ask). Answer each question yourself, then call check_ai_answer with the keyword and your answer. Pass your assistant name to see when you last checked each keyword.')]
#[IsReadOnly]
class GetAiCheckPrompts extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'assistant' => $schema->string()->description('Your assistant name (claude, chatgpt, grok…). Optional: adds your last check per keyword.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));
        $request->validate(['assistant' => ['nullable', 'string', 'max:60']]);

        $assistant = $request->get('assistant') !== null ? AiAssistant::normalize((string) $request->get('assistant')) : null;

        $keywords = $domain->keywords()->with('aiChecks')->orderBy('keyword')->get()->map(function (Keyword $keyword) use ($assistant) {
            $last = $assistant === null ? null : $keyword->aiChecks->first(fn ($check) => in_array($assistant, $check->providers ?? [], true));
            $result = $last?->resultFor($assistant);

            return array_filter([
                'keyword' => $keyword->keyword,
                'prompt' => LlmVisibilityPrompt::userPrompt($keyword->keyword),
                'last_checked_at' => $last !== null ? self::iso($last->created_at) : null,
                'last_mentioned' => $result !== null ? (bool) ($result['present'] ?? false) : null,
            ], fn ($value) => $value !== null);
        });

        return Response::json([
            'domain' => $domain->domain,
            'count' => $keywords->count(),
            'keywords' => $keywords->values()->all(),
            'instructions' => $keywords->isEmpty()
                ? __('Bu domain\'de takip edilen kelime yok. check_ai_answer\'a bir keyword verirseniz kelime eklenir.')
                : __('Her soruyu domain\'i bilmiyormuş gibi kendiniz cevaplayın, sonra check_ai_answer\'ı keyword, answer ve assistant ile çağırın.'),
        ]);
    }
}
