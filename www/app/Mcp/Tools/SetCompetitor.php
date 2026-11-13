<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\CompetitorBlacklist;
use App\Models\Domain;
use App\Models\DomainCompetitor;
use App\Services\Analytics\CompetitorAnalyzer;
use App\Services\Competitors\CompetitorManager;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('set_competitor')]
#[Description('Marks a domain as a real competitor ("tracked") or as not a competitor ("ignored") for a site. Works for domains found in get_competitors SERP discovery and for any competitor domain you enter manually. Tracked competitors get their top-ranking keywords fetched in the background (DataForSEO ranked keywords, or a free crawl of their site); read the result with get_competitor_keyword_gap after a minute or two. Pass refresh=true to re-fetch an already tracked competitor. Ignored competitors are hidden from SERP discovery. Requires the "write" permission.')]
class SetCompetitor extends SeoTool
{
    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Your site: domain name (example.com) or numeric id from list_domains.')->required(),
            'competitor' => $schema->string()->description('Competitor domain, e.g. rival.com (a URL is also accepted).')->required(),
            'status' => $schema->string()->enum(DomainCompetitor::STATUSES)->description('"tracked" = real competitor (default), "ignored" = not a competitor.'),
            'refresh' => $schema->boolean()->description('Re-fetch the competitor\'s keywords even if they were fetched before.'),
        ];
    }

    public function handle(Request $request, CompetitorManager $manager): Response
    {
        $user = $this->authorizedUser();
        $domain = $this->resolveDomain($user, $request->get('domain'));

        $data = $request->validate([
            'competitor' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(DomainCompetitor::STATUSES)],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $host = DomainHelper::fromFreeText($data['competitor']);
        if ($host === null) {
            throw new ToolError(__('Geçerli bir domain girin (örnek: rakip.com).'));
        }
        if (DomainHelper::equals($host, $domain->domain)) {
            throw new ToolError(__('Kendi domaininizi rakip olarak ekleyemezsiniz.'));
        }
        if (($data['status'] ?? DomainCompetitor::TRACKED) === DomainCompetitor::TRACKED && CompetitorBlacklist::matches($host)) {
            throw new ToolError(__(':domain genel rakip kara listesinde; rakip olarak eklenemez.', ['domain' => $host]));
        }

        $existing = $domain->competitors()->where('competitor_domain', $host)->first();
        $source = $existing?->source
            ?? (in_array($host, $this->serpDomains($domain), true) ? DomainCompetitor::SOURCE_SERP : DomainCompetitor::SOURCE_MANUAL);

        $competitor = $manager->upsert($domain, $host, $data['status'] ?? DomainCompetitor::TRACKED, $source, $user);

        if (($data['refresh'] ?? false) && $competitor->isTracked()) {
            $manager->requestKeywords($competitor);
            $competitor->refresh();
        }

        return Response::json([
            'domain' => $domain->domain,
            'competitor' => $competitor->competitor_domain,
            'status' => $competitor->status,
            'source' => $competitor->source,
            'keywords_fetching' => $competitor->isFetchingKeywords(),
            'message' => $competitor->isTracked()
                ? __(':domain rakip olarak eklendi; öne çıktığı anahtar kelimeler arka planda çekiliyor.', ['domain' => $host])
                : __(':domain rakip değil olarak işaretlendi ve keşif listesinden gizlendi.', ['domain' => $host]),
            'url' => route('domains.show', $domain) . '#tab-competitors',
        ]);
    }

    /**
     * @return list<string>
     */
    private function serpDomains(Domain $domain): array
    {
        $domain->load('keywords.latestCheck');
        $serp = (new CompetitorAnalyzer())->frequentCompetitors($domain->domain, $domain->keywords);

        return array_map(DomainHelper::normalize(...), array_column($serp['competitors'], 'domain'));
    }
}
