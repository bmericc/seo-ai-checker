<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\IndexNowSubmission;
use App\Services\IndexNow\IndexNowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_indexnow_status')]
#[Description('Shows whether IndexNow (instant indexing for Bing, Yandex, Seznam, Naver) is set up for a domain: where the key file must be hosted, whether it was verified, and recent submissions.')]
#[IsReadOnly]
class GetIndexNowStatus extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
        ];
    }

    public function handle(Request $request, IndexNowService $indexNow): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $hasKey = $domain->indexnow_key !== null;

        return Response::json([
            'domain' => $domain->domain,
            'key_created' => $hasKey,
            'key_file_url' => $hasKey ? $indexNow->keyFileUrl($domain) : null,
            // Anahtar zaten sitede herkese acik barindirilir; gizli degil.
            'key_file_content' => $domain->indexnow_key,
            'verified' => $domain->indexnow_verified_at !== null,
            'verified_at' => self::iso($domain->indexnow_verified_at),
            'setup_hint' => $hasKey
                ? __('Anahtar dosyasını indirip sitenin köküne koyun, ardından arayüzden doğrulayın.')
                : __('Henüz anahtar yok; domain sayfasındaki "Hızlı indeksleme" sekmesinden oluşturun.'),
            'recent_submissions' => $domain->indexNowSubmissions()->limit(5)->get()->map(fn (IndexNowSubmission $s) => [
                'at' => self::iso($s->created_at),
                'source' => $s->source,
                'url_count' => $s->url_count,
                'ok' => $s->ok,
                'status_code' => $s->status_code,
                'message' => $s->message,
            ])->values(),
            'url' => route('domains.show', $domain) . '#tab-indexnow',
        ]);
    }
}
