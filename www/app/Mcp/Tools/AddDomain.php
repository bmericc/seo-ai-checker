<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Jobs\RunDomainSiteCheck;
use App\Jobs\RunDomainWhoisLookup;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_domain')]
#[Description('Adds a domain to your account and starts the first site check (robots.txt AI crawlers, sitemap, HTTPS, speed, llms.txt) in the background. Requires the "write" permission.')]
class AddDomain extends SeoTool
{
    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain or site URL, e.g. example.com or https://www.example.com/page.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $request->validate(['domain' => ['required', 'string', 'max:255']]);

        $name = DomainHelper::fromFreeText((string) $request->get('domain'));
        if ($name === null) {
            throw new ToolError(__('Geçerli bir domain girin (örnek: example.com).'));
        }

        $existing = $user->domains()->where('domain', $name)->first();
        if ($existing !== null) {
            return Response::json([
                'created' => false,
                'id' => $existing->id,
                'domain' => $existing->domain,
                'message' => __(':domain zaten kayıtlı.', ['domain' => $name]),
                'url' => route('domains.show', $existing),
            ]);
        }

        $domain = $user->domains()->create(['domain' => $name]);

        RunDomainWhoisLookup::dispatch($domain->id);
        RunDomainSiteCheck::dispatch($domain->id);

        return Response::json([
            'created' => true,
            'id' => $domain->id,
            'domain' => $domain->domain,
            'message' => __(':domain eklendi. Site kontrolü arka planda başladı; birkaç dakika sonra get_domain ile sonuca bakın.', ['domain' => $name]),
            'url' => route('domains.show', $domain),
        ]);
    }
}
