<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Keyword;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_keywords')]
#[Description('Lists a domain\'s tracked keywords with the latest Google position, whether an AI Overview appeared and cited the site, and Lighthouse scores.')]
#[IsReadOnly]
class ListKeywords extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $keywords = $domain->keywords()->with('latestCheck')->orderBy('keyword')->get();

        return Response::json([
            'domain' => $domain->domain,
            'count' => $keywords->count(),
            'keywords' => $keywords->map(fn (Keyword $keyword) => $this->row($keyword))->values(),
        ]);
    }

    private function row(Keyword $keyword): array
    {
        $check = $keyword->latestCheck;

        return [
            'id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'target_url' => $keyword->targetUrl(),
            'last_checked_at' => self::iso($check?->created_at),
            'google_position' => $check?->target_position,
            'serp_blocked' => $check?->blocked,
            'ai_overview_present' => $check?->ai_overview_present,
            'ai_overview_cites_site' => $check?->ai_overview_target_cited,
            'lighthouse' => $check === null ? null : [
                'performance' => $check->lighthouse_performance,
                'seo' => $check->lighthouse_seo,
                'accessibility' => $check->lighthouse_accessibility,
                'best_practices' => $check->lighthouse_best_practices,
            ],
            'url' => route('keywords.show', $keyword),
        ];
    }
}
