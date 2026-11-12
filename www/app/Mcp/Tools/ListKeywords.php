<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Keyword;
use App\Services\Analytics\AiOverviewVolatility;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_keywords')]
#[Description('Lists a domain\'s tracked keywords with the latest desktop and mobile Google position, search volume / keyword difficulty / CPC / intent (DataForSEO), whether an AI Overview appeared and cited the site, AI Overview stability over the last 10 checks (trigger rate, citation persistence, change rate), and Lighthouse scores.')]
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
        $stability = collect(AiOverviewVolatility::forKeywords($keywords)['keywords'])->keyBy('keyword_id');

        return Response::json([
            'domain' => $domain->domain,
            'count' => $keywords->count(),
            'keywords' => $keywords->map(fn (Keyword $keyword) => $this->row($keyword, $stability->get($keyword->id)))->values(),
        ]);
    }

    private function row(Keyword $keyword, ?array $stability): array
    {
        $check = $keyword->latestCheck;

        return [
            'id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'target_url' => $keyword->targetUrl(),
            'last_checked_at' => self::iso($check?->created_at),
            'google_position' => $check?->target_position,
            'google_mobile_position' => $check?->mobile_target_position,
            'serp_blocked' => $check?->blocked,
            'metrics' => $keyword->metrics_fetched_at === null ? null : [
                'search_volume' => $keyword->search_volume,
                'keyword_difficulty' => $keyword->keyword_difficulty,
                'cpc_usd' => $keyword->cpc,
                'competition' => $keyword->competition,
                'search_intent' => $keyword->search_intent,
                'fetched_at' => self::iso($keyword->metrics_fetched_at),
            ],
            'metrics_error' => $keyword->metrics_error,
            'ai_overview_present' => $check?->ai_overview_present,
            'ai_overview_cites_site' => $check?->ai_overview_target_cited,
            // Son 10 engellenmemis kontrol: AIO cikma / kaynak olma / durum degisim yuzdesi.
            'ai_overview_stability' => ($stability['checks'] ?? 0) === 0 ? null : [
                'checks' => $stability['checks'],
                'ai_overview_rate_pct' => $stability['aio_rate'],
                'citation_rate_pct' => $stability['citation_rate'],
                'change_rate_pct' => $stability['change_rate'],
                'level' => $stability['level'],
            ],
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
