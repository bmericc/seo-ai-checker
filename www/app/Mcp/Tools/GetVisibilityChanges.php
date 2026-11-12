<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Analytics\VisibilityChanges;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_visibility_changes')]
#[Description('What got worse or better for a domain over the last N days (default 7): per keyword, Google position changes, lost/gained AI Overview citations and AI Overviews appearing without citing the site; per AI assistant, the change in how often the site is mentioned versus the previous period of the same length; Search Console click changes and site-check changes (robots.txt AI crawlers, sitemap, llms.txt, security headers, speed). Losses and gains are sorted by impact (0-100, highest first). Use it to answer "what was the biggest visibility loss this week?". Only already-recorded checks are compared; run_keyword_check / run_domain_check refresh the data.')]
#[IsReadOnly]
class GetVisibilityChanges extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'days' => $schema->integer()->description(sprintf('Comparison window in days (1-%d, default %d).', VisibilityChanges::MAX_DAYS, VisibilityChanges::DEFAULT_DAYS)),
        ];
    }

    public function handle(Request $request, VisibilityChanges $changes): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $result = $changes->forDomain($domain, (int) ($request->get('days') ?? VisibilityChanges::DEFAULT_DAYS));

        return Response::json([
            'domain' => $domain->domain,
            ...$result,
            'note' => $result['compared_keywords'] === 0 && $result['losses'] === [] && $result['gains'] === []
                ? 'No keyword has a check both before and inside this window, so nothing could be compared. Try a longer window or run new checks.'
                : null,
            'url' => route('domains.show', $domain),
        ]);
    }
}
