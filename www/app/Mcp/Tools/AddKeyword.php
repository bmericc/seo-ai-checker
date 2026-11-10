<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_keyword')]
#[Description('Starts tracking a search keyword for a domain (Google position, AI Overview citation, on-page and Lighthouse). Optionally pin the page that should rank. Requires the "write" permission. Run run_keyword_check afterwards to get the first result.')]
class AddKeyword extends SeoTool
{
    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'keyword' => $schema->string()->description('The search phrase, e.g. "best running shoes".')->required(),
            'url' => $schema->string()->description('Optional page URL on this domain that should rank for the keyword.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $phrase = trim($data['keyword']);
        $existing = $domain->keywords()->where('keyword', $phrase)->first();
        if ($existing !== null) {
            return Response::json([
                'created' => false,
                'id' => $existing->id,
                'keyword' => $existing->keyword,
                'message' => __('":keyword" zaten takip ediliyor.', ['keyword' => $phrase]),
            ]);
        }

        $keyword = $domain->keywords()->create([
            'keyword' => $phrase,
            'url' => $data['url'] ?? null,
        ]);

        return Response::json([
            'created' => true,
            'id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'domain' => $domain->domain,
            'message' => __('":keyword" eklendi.', ['keyword' => $phrase]),
            'url' => route('keywords.show', $keyword),
        ]);
    }
}
