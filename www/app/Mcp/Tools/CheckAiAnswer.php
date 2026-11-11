<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Domain;
use App\Models\Keyword;
use App\Services\Llm\LlmMentions;
use App\Services\Llm\LlmVisibilityPrompt;
use App\Services\Llm\PromptExplorer;
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
 * saklar. Anahtar kelime verilirse sonuc o kelimeye baglanir; kelime
 * domain'de takip edilmiyorsa eklenir.
 */
#[Name('check_ai_answer')]
#[Description(<<<'TEXT'
    Manual AI visibility check without API keys. Use it when the user gives a question or keyword and a domain and asks whether an AI assistant's answer mentions that domain.
    How to use: first answer the prompt yourself exactly as you would for an ordinary user who never mentioned the domain (use web search if you normally would, do not steer the answer towards the domain), then call this tool with your full answer text and the URLs you cited.
    Give either a keyword or a prompt. With a keyword, the prompt to answer is the one get_ai_check_prompts returns for it (the same question the API checks ask); a keyword the domain does not track yet is added to its keywords. When the user names no keyword or prompt, call get_ai_check_prompts first and check every keyword it returns.
    The tool reports whether the domain appears in the answer or the cited URLs, lists the other sites mentioned and saves the result as history.
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
            'keyword' => $schema->string()->description('Keyword the check is for. If the prompt is omitted, the standard prompt for this keyword is used. Added to the domain\'s keywords when missing.'),
            'prompt' => $schema->string()->description('The question that was answered, exactly as a user would ask it. Optional when a keyword is given.'),
            'answer' => $schema->string()->description('Your full answer to the prompt, verbatim.')->required(),
            'assistant' => $schema->string()
                ->description('Which assistant wrote the answer, by its own name: claude, chatgpt, gemini, perplexity, grok, deepseek, copilot, mistral… Any name is accepted.')->required(),
            'model' => $schema->string()->description('Model name, e.g. claude-opus-5-5 or grok-5. Optional.'),
            'citations' => $schema->array()->items($schema->string())->description('URLs cited in the answer (web search sources). Optional.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'prompt' => ['nullable', 'string', 'max:' . PromptExplorer::MAX_PROMPT_LENGTH],
            'answer' => ['required', 'string', 'max:' . self::MAX_ANSWER_LENGTH],
            'assistant' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:100'],
            'citations' => ['nullable', 'array', 'max:' . self::MAX_CITATIONS],
            'citations.*' => ['string', 'max:2000'],
        ]);

        $assistant = AiAssistant::normalize($data['assistant']);
        if ($assistant === null) {
            throw new ToolError(__('Geçersiz asistan adı. Harf, rakam ve tire kullanın (örneğin grok, deepseek).'));
        }

        $phrase = trim((string) ($data['keyword'] ?? ''));
        $prompt = trim((string) ($data['prompt'] ?? ''));
        if ($phrase === '' && $prompt === '') {
            throw new ToolError(__('Bir anahtar kelime ya da soru verin. Domain\'in kelimelerini ve sorulacak soruları get_ai_check_prompts ile alabilirsiniz.'));
        }

        [$keyword, $keywordCreated] = $phrase === '' ? [null, false] : $this->findOrCreateKeyword($domain, $phrase);
        if ($prompt === '') {
            $prompt = LlmVisibilityPrompt::userPrompt($keyword->keyword);
        }

        $answer = trim($data['answer']);
        $citations = array_values(array_unique(array_filter(array_map('trim', $data['citations'] ?? []))));

        $inText = LlmMentions::mentions($answer, $domain->domain);
        $citedUrls = array_values(array_filter($citations, fn (string $url) => self::isOwnHost($url, $domain->domain)));
        $present = $inText || $citedUrls !== [];

        $exploration = $domain->promptExplorations()->create([
            'user_id' => $user->id,
            'keyword_id' => $keyword?->id,
            'prompt' => $prompt,
            'providers' => [$assistant],
            'results' => [
                $assistant => [
                    'present' => $present,
                    'response' => $answer,
                    'error' => null,
                    'model' => $data['model'] ?? null,
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
            'keyword' => $keyword?->keyword,
            'keyword_created' => $keyword !== null ? $keywordCreated : null,
            'prompt' => $prompt,
            'mentions_domain' => $present,
            'mentioned_in_text' => $inText,
            'cited_urls' => $citedUrls,
            'other_sites_mentioned' => $exploration->mentionedSites(),
            'message' => $present
                ? __(':domain cevapta geçiyor.', ['domain' => $domain->domain])
                : __(':domain cevapta geçmiyor.', ['domain' => $domain->domain]),
            'url' => route('domains.prompts.show', [$domain, $exploration]),
        ], fn ($value) => $value !== null));
    }

    /**
     * Buyuk/kucuk harf ve bosluk farkini yok sayarak domain'in kelimesini
     * bulur; yoksa ekler.
     *
     * @return array{0: Keyword, 1: bool}
     */
    private function findOrCreateKeyword(Domain $domain, string $phrase): array
    {
        $phrase = (string) preg_replace('/\s+/u', ' ', $phrase);
        $needle = mb_strtolower($phrase);

        $existing = $domain->keywords()->get()->first(fn (Keyword $k) => mb_strtolower(trim($k->keyword)) === $needle);
        if ($existing !== null) {
            return [$existing, false];
        }

        return [$domain->keywords()->create(['keyword' => $phrase]), true];
    }

    private static function isOwnHost(string $url, string $domain): bool
    {
        $host = LlmMentions::normalizeHost((string) parse_url($url, PHP_URL_HOST));
        $own = LlmMentions::normalizeHost($domain);

        return $host !== '' && ($host === $own || str_ends_with($host, '.' . $own));
    }
}
