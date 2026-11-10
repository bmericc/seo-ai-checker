<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\DomainLlmApiKey;
use App\Services\Llm\PromptExplorer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('explore_prompt')]
#[Description('Admin only. Asks ChatGPT, Claude, Gemini and/or Perplexity a free-form question (using the domain\'s own API keys) and records whether each answer mentions the domain. Runs in the background; read the answers with get_prompt_exploration.')]
class ExplorePrompt extends SeoTool
{
    protected bool $writes = true;

    protected bool $adminOnly = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'prompt' => $schema->string()->description('The question a real user might ask, e.g. "Which accounting software is best for small shops in Turkey?"')->required(),
            'providers' => $schema->array()->items($schema->string()->enum(DomainLlmApiKey::PROVIDERS))
                ->description('Which assistants to ask. Default: every provider with an API key on this domain.'),
        ];
    }

    public function handle(Request $request, PromptExplorer $explorer): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:'.PromptExplorer::MAX_PROMPT_LENGTH],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['string', 'in:'.implode(',', DomainLlmApiKey::PROVIDERS)],
        ]);

        try {
            $exploration = $explorer->start($domain, $user, $data['prompt'], $data['providers'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw new ToolError($e->getMessage());
        }

        return Response::json([
            'exploration_id' => $exploration->id,
            'providers' => $exploration->providers,
            'message' => __('Sorular gönderildi. Yaklaşık bir dakika sonra get_prompt_exploration ile cevapları okuyun.'),
            'url' => route('domains.prompts.show', [$domain, $exploration]),
        ]);
    }
}
