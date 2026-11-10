<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\IndexNow\IndexNowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('submit_indexnow')]
#[Description('Notifies Bing, Yandex, Seznam and Naver about new or changed pages via IndexNow. Pass specific URLs, or omit them to submit every URL in the domain\'s sitemap. The key file must already be hosted on the site (see get_indexnow_status). Requires the "write" permission.')]
class SubmitIndexNow extends SeoTool
{
    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'urls' => $schema->array()->items($schema->string())->max(10000)->description('Full page URLs on this domain. Omit to submit the sitemap.'),
        ];
    }

    public function handle(Request $request, IndexNowService $indexNow): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $request->validate([
            'urls' => ['nullable', 'array', 'max:10000'],
            'urls.*' => ['string', 'max:2048'],
        ]);

        if ($domain->indexnow_key === null) {
            throw new ToolError(__('Bu domain için IndexNow anahtarı yok. Önce domain sayfasındaki "Hızlı indeksleme" sekmesinden anahtar oluşturup dosyayı sitenize koyun.'));
        }

        $urls = $request->get('urls');
        $source = is_array($urls) && $urls !== [] ? 'manual' : 'sitemap';
        $submission = $indexNow->submit($domain, $source === 'manual' ? $urls : $indexNow->sitemapUrls($domain), $source);

        return Response::json([
            'ok' => $submission->ok,
            'source' => $source,
            'url_count' => $submission->url_count,
            'status_code' => $submission->status_code,
            'message' => $submission->message,
        ]);
    }
}
