<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Support\DomainHealth;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_domain')]
#[Description('Returns one domain\'s SEO health: score, prioritized issues with how to fix them, AI crawler/sitemap/HTTPS/speed/llms.txt checks, AI Overview citation stats, domain age and IndexNow status.')]
#[IsReadOnly]
class GetDomain extends SeoTool
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
        $domain->load(['fact', 'latestDomainCheck', 'keywords.latestCheck']);

        $health = DomainHealth::for($domain);

        return Response::json([
            'id' => $domain->id,
            'domain' => $domain->domain,
            'site_url' => $domain->rootUrl(),
            'health' => [
                'checked' => $health->hasData,
                'score' => $health->score,
                'summary' => $health->summary(),
                'checks' => $health->checks,
                'issues' => $health->issues,
            ],
            'last_site_check_at' => self::iso($domain->latestDomainCheck?->created_at),
            'keyword_count' => $domain->keywords->count(),
            'ai_overview' => $domain->aiVisibilitySnapshot(),
            'domain_age_years' => $domain->whoisAgeInYears(),
            'indexnow_verified' => $domain->indexnow_verified_at !== null,
            'url' => route('domains.show', $domain),
        ]);
    }
}
