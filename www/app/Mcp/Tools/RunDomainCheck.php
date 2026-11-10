<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Jobs\RunDomainSiteCheck;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('run_domain_check')]
#[Description('Re-runs the site health check for a domain in the background (fresh data, no cache). Results appear in get_domain after a few minutes. Requires the "write" permission.')]
#[IsIdempotent]
class RunDomainCheck extends SeoTool
{
    protected bool $writes = true;

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

        RunDomainSiteCheck::dispatch($domain->id, forceFresh: true);

        return Response::json([
            'queued' => true,
            'domain' => $domain->domain,
            'message' => __('Site kontrolü sıraya alındı. Birkaç dakika sonra get_domain ile sonuca bakın.'),
        ]);
    }
}
