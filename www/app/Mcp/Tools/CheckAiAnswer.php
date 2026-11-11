<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Domain;
use App\Models\Keyword;
use App\Services\Llm\LlmMentions;
use App\Services\Llm\LlmVisibilityPrompt;
use App\Support\AiAssistant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Manuel kontrol: API anahtari kullanmadan, MCP istemcisindeki yapay
 * zekanin (Claude, ChatGPT, Grok...) kendi verdigi cevabi alir, domain'in
 * gecip gecmedigini bulur ve gecmis icin Prompt Explorer kaydi olarak
 * saklar. Soru her zaman API kontrolleriyle ayni formattadir
 * (LlmVisibilityPrompt); serbest soru kabul edilmez. Kelime domain'de
 * kayitli olmali - yeni kelimeyi once get_ai_check_prompts ekler.
 */
#[Name('check_ai_answer')]
#[Description(<<<'TEXT'
    Manual AI visibility check of a tracked keyword without API keys, in the same format as the API checks.
    Steps: 1) call get_ai_check_prompts (with the keyword if the user named one; a new keyword is added to the domain's keywords there first). 2) Follow its system_instruction and answer its prompt yourself as you would for an ordinary user who never mentioned the domain (use web search if you normally would, never steer the answer towards the domain). 3) Answer its follow_up_prompt as the next turn of the same conversation. 4) Call this tool with the keyword, both answers, the URLs you cited, and your assistant name and exact model (both required).
    The keyword must already be tracked by the domain. The tool reports whether the domain appears in the answers or cited URLs, lists the other sites mentioned and saves the result as history.
    TEXT)]
class CheckAiAnswer extends SeoTool
{
    private const MAX_ANSWER_LENGTH = 50000;

    private const MAX_CITATIONS = 50;

    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'keyword' => $schema->string()->description('A keyword the domain tracks (see get_ai_check_prompts).')->required(),
            'answer' => $schema->string()->description('Your full answer to the keyword\'s prompt, verbatim.')->required(),
            'follow_up_answer' => $schema->string()->description('Your answer to the follow_up_prompt, verbatim. Optional but recommended.'),
            'assistant' => $schema->string()
                ->description('Required. Which assistant wrote the answer, by its own name: claude, chatgpt, gemini, perplexity, grok, deepseek, copilot, mistral… Any name is accepted.')->required(),
            'model' => $schema->string()->description('Required. The exact model that wrote the answer, e.g. claude-opus-5-5, gpt-5.5, grok-5.')->required(),
            'citations' => $schema->array()->items($schema->string())->description('URLs cited in the answer (web search sources). Optional.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:' . self::MAX_ANSWER_LENGTH],
            'follow_up_answer' => ['nullable', 'string', 'max:' . self::MAX_ANSWER_LENGTH],
            'assistant' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:100'],
            'citations' => ['nullable', 'array', 'max:' . self::MAX_CITATIONS],
            'citations.*' => ['string', 'max:2000'],
        ]);

        $assistant = AiAssistant::normalize($data['assistant']);
        if ($assistant === null) {
            throw new ToolError(__('Geçersiz asistan adı. Harf, rakam ve tire kullanın (örneğin grok, deepseek).'));
        }

        $keyword = self::findKeyword($domain, $data['keyword']);
        if ($keyword === null) {
            throw new ToolError(__('":keyword" bu domain\'de takip edilmiyor. Önce get_ai_check_prompts\'u bu kelimeyle çağırın; kelime eklenir ve sorulacak soru döner.', ['keyword' => trim($data['keyword'])]));
        }
        if (trim($data['model']) === '') {
            throw new ToolError(__('Model bilgisi zorunlu (örneğin claude-opus-5-5, grok-5).'));
        }
        $prompt = LlmVisibilityPrompt::userPrompt($keyword->keyword);

        $answer = trim($data['answer']);
        $followUp = trim((string) ($data['follow_up_answer'] ?? ''));
        $citations = array_values(array_unique(array_filter(array_map('trim', $data['citations'] ?? []))));

        $inText = LlmMentions::mentions($answer, $domain->domain);
        $inFollowUp = $followUp !== '' && LlmMentions::mentions($followUp, $domain->domain);
        $citedUrls = array_values(array_filter($citations, fn (string $url) => self::isOwnHost($url, $domain->domain)));
        $present = $inText || $citedUrls !== [];

        $exploration = $domain->promptExplorations()->create([
            'user_id' => $user->id,
            'keyword_id' => $keyword->id,
            'prompt' => $prompt,
            'providers' => [$assistant],
            'results' => [
                $assistant => [
                    'present' => $present,
                    'response' => $answer,
                    'error' => null,
                    'prompt' => $prompt,
                    'follow_up_prompt' => $followUp !== '' ? LlmVisibilityPrompt::FOLLOW_UP_PROMPT : null,
                    'follow_up_response' => $followUp !== '' ? $followUp : null,
                    'follow_up_present' => $inFollowUp,
                    'model' => trim($data['model']),
                    'citations' => $citations,
                    'source' => 'manual',
                ],
            ],
            'completed_at' => now(),
        ]);
        $exploration->setRelation('domain', $domain);

        return Response::json(array_filter([
            'exploration_id' => $exploration->id,
            'domain' => $domain->domain,
            'assistant' => $assistant,
            'assistant_label' => AiAssistant::label($assistant),
            'model' => trim($data['model']),
            'keyword' => $keyword->keyword,
            'prompt' => $prompt,
            'mentions_domain' => $present,
            'mentioned_in_text' => $inText,
            'mentioned_in_follow_up' => $followUp !== '' ? $inFollowUp : null,
            'cited_urls' => $citedUrls,
            'other_sites_mentioned' => $exploration->mentionedSites(),
            'message' => $present
                ? __(':domain cevapta geçiyor.', ['domain' => $domain->domain])
                : __(':domain cevapta geçmiyor.', ['domain' => $domain->domain]),
            'url' => route('domains.prompts.show', [$domain, $exploration]),
        ], fn ($value) => $value !== null));
    }

    /**
     * Buyuk/kucuk harf ve bosluk farkini yok sayarak domain'in kelimesini bulur.
     */
    public static function findKeyword(Domain $domain, string $phrase): ?Keyword
    {
        $needle = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($phrase)));

        return $domain->keywords()->get()->first(fn (Keyword $k) => mb_strtolower(trim($k->keyword)) === $needle);
    }

    private static function isOwnHost(string $url, string $domain): bool
    {
        $host = LlmMentions::normalizeHost((string) parse_url($url, PHP_URL_HOST));
        $own = LlmMentions::normalizeHost($domain);

        return $host !== '' && ($host === $own || str_ends_with($host, '.' . $own));
    }
}
