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

/**
 * Manuel kontrolun ilk adimi: sorulacak sorulari API kontrolleriyle ayni
 * formatta (sistem talimati + kelime sorusu + takip sorusu) verir.
 * Kelime verilip domain'de yoksa once kelimelere eklenir ("write" izni).
 * Kelime verilmezse domain'in tum kelimeleri doner.
 */
#[Name('get_ai_check_prompts')]
#[Description('First step of a manual AI visibility check. Returns the system_instruction, follow_up_prompt and, per keyword, the prompt to answer - the same format the API checks use. With a keyword, only that keyword is returned and a keyword the domain does not track yet is added to its keywords first (needs the "write" permission). Without a keyword, every tracked keyword is returned. Then answer each prompt yourself and record it with check_ai_answer.')]
class GetAiCheckPrompts extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'keyword' => $schema->string()->description('A keyword to check. Added to the domain\'s keywords when missing. Optional: without it every tracked keyword is returned.'),
            'assistant' => $schema->string()->description('Your assistant name (claude, chatgpt, grok…). Optional: adds your last check per keyword.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));
        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'assistant' => ['nullable', 'string', 'max:60'],
        ]);

        $assistant = isset($data['assistant']) ? AiAssistant::normalize($data['assistant']) : null;
        $created = false;

        $phrase = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['keyword'] ?? '')));
        if ($phrase !== '') {
            $keyword = CheckAiAnswer::findKeyword($domain, $phrase);
            if ($keyword === null) {
                if (!$this->canWrite($user)) {
                    throw new ToolError(__('Yeni kelime eklemek için "write" izni gerekiyor. Bağlantıyı yazma izniyle yeniden yetkilendirin.'));
                }
                $this->ensureCanEdit($user, $domain);
                $keyword = $domain->keywords()->create(['keyword' => $phrase]);
                $created = true;
            }
            $keywords = collect([$keyword->load('aiChecks')]);
        } else {
            $keywords = $domain->keywords()->with('aiChecks')->orderBy('keyword')->get();
        }

        $items = $keywords->map(function (Keyword $keyword) use ($assistant) {
            $last = $assistant === null ? null : $keyword->aiChecks->first(fn ($check) => in_array($assistant, $check->providers ?? [], true));
            $result = $last?->resultFor($assistant);

            return array_filter([
                'keyword' => $keyword->keyword,
                'prompt' => LlmVisibilityPrompt::userPrompt($keyword->keyword),
                'last_checked_at' => $last !== null ? self::iso($last->created_at) : null,
                'last_model' => $result['model'] ?? null,
                'last_mentioned' => $result !== null ? (bool) ($result['present'] ?? false) : null,
            ], fn ($value) => $value !== null);
        });

        return Response::json(array_filter([
            'domain' => $domain->domain,
            'keyword_created' => $phrase !== '' ? $created : null,
            'system_instruction' => LlmVisibilityPrompt::SYSTEM_PROMPT,
            'follow_up_prompt' => LlmVisibilityPrompt::FOLLOW_UP_PROMPT,
            'count' => $items->count(),
            'keywords' => $items->values()->all(),
            'instructions' => $items->isEmpty()
                ? __('Bu domain\'de takip edilen kelime yok. get_ai_check_prompts\'u bir keyword ile çağırırsanız kelime eklenir.')
                : __('Her kelime için system_instruction\'a uyarak prompt\'u domain\'i bilmiyormuş gibi kendiniz cevaplayın, sonra follow_up_prompt\'u aynı sohbetin devamı olarak cevaplayın ve check_ai_answer\'ı keyword, answer, follow_up_answer, assistant ve model ile çağırın.'),
        ], fn ($value) => $value !== null));
    }
}
