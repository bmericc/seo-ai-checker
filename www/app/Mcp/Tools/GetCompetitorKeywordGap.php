<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\DomainCompetitor;
use App\Services\Analytics\CompetitorKeywordGap;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_competitor_keyword_gap')]
#[Description('Cross-check of the site\'s selected competitors (see set_competitor): keywords those competitors rank for (or feature prominently on their site) that the site does NOT track yet. Keywords shared by several competitors come first, then by monthly search volume. Each row lists which competitors rank and at what position. Use add_keyword to start tracking a gap keyword. Also returns each selected competitor\'s fetch status (source: dataforseo = real Google rankings, crawl = site crawl without volume/position).')]
#[IsReadOnly]
class GetCompetitorKeywordGap extends SeoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Domain name (example.com) or numeric id from list_domains.')->required(),
            'competitor' => $schema->string()->description('Optional: only gaps where this selected competitor ranks.'),
            'limit' => $schema->integer()->description('Max rows (default 50, max ' . CompetitorKeywordGap::MAX_ROWS . ').'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));
        $domain->load('keywords');

        $tracked = $domain->competitors()->where('status', DomainCompetitor::TRACKED)->get();
        $limit = max(1, min(CompetitorKeywordGap::MAX_ROWS, (int) ($request->get('limit') ?? 50)));

        $rows = CompetitorKeywordGap::forDomain($domain, $tracked);

        $only = trim((string) $request->get('competitor', ''));
        if ($only !== '') {
            $host = DomainHelper::fromFreeText($only) ?? strtolower($only);
            $rows = array_values(array_filter($rows, fn (array $row) => in_array($host, array_column($row['competitors'], 'domain'), true)));
        }

        return Response::json([
            'domain' => $domain->domain,
            'selected_competitors' => $tracked->map(fn (DomainCompetitor $c) => [
                'domain' => $c->competitor_domain,
                'source' => $c->source,
                'keywords_source' => $c->keywords_source,
                'keywords_count' => count($c->keywords ?? []),
                'keywords_fetched_at' => self::iso($c->keywords_fetched_at),
                'keywords_fetching' => $c->isFetchingKeywords(),
                'error' => $c->keywords_error,
            ])->values()->all(),
            'gap_count' => count($rows),
            'gaps' => array_map(fn (array $row) => [
                'keyword' => $row['keyword'],
                'competitor_count' => $row['competitor_count'],
                'search_volume' => $row['search_volume'],
                'best_competitor_position' => $row['best_position'],
                'competitors' => $row['competitors'],
            ], array_slice($rows, 0, $limit)),
            'message' => $tracked->isEmpty() ? __('Çapraz kontrol için en az bir rakip seçin.') : null,
            'url' => route('domains.show', $domain) . '#tab-competitors',
        ]);
    }
}
