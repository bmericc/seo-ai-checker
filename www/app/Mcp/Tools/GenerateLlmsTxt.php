<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Llms\LlmsTxtGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('generate_llms_txt')]
#[Description('Builds an llms.txt draft (llmstxt.org format) from the pages discovered in the domain\'s sitemap, grouped by URL section. Titles/descriptions come from pages analyzed in the page report, otherwise they are derived from the URL. Nothing is saved or published: improve the draft (site summary, page descriptions) and give it to the user to upload as /llms.txt at their site root.')]
#[IsReadOnly]
class GenerateLlmsTxt extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
        ];
    }

    public function handle(Request $request, LlmsTxtGenerator $generator): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));
        $domain->load('fact');

        $pages = $generator->activePages($domain);
        if ($pages->isEmpty()) {
            throw new ToolError(__('Sitemap\'ten keşfedilmiş sayfa yok. Önce run_domain_check ile site kontrolünü çalıştırın.'));
        }

        $existing = $domain->fact?->llms_txt ?? [];

        return Response::json([
            'domain' => $domain->domain,
            'page_count' => $pages->count(),
            'pages_with_metadata' => $pages->whereNotNull('onpage_checked_at')->count(),
            'max_pages' => LlmsTxtGenerator::MAX_PAGES,
            'existing_llms_txt' => ($existing['found'] ?? false) ? ['url' => $existing['url'] ?? null, 'preview' => $existing['preview'] ?? null] : null,
            'draft' => $generator->generate($domain, $pages),
            'url' => route('domains.llms-txt', $domain),
        ]);
    }
}
