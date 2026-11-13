<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\DomainCompetitor;
use App\Services\Analytics\CompetitorAnalyzer;
use App\Services\Analytics\LlmShareOfModel;
use App\Support\AiAssistant;
use App\Support\Domain as DomainHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_competitors')]
#[Description('Returns a domain\'s competitors from two angles: (1) Google SERP - domains that rank alongside the site across tracked keywords, with average/best position; (2) Share of Model - for AI answers recorded in the last 90 days (AI visibility checks, Prompt Explorer, check_ai_answer), the percentage of each assistant\'s answers that mention the site and each competitor. Only competitors mentioned by domain name are counted. Competitors the user selected (set_competitor) are listed in selected_competitors and drive the Share of Model rows; ignored ones are left out of SERP discovery. For keywords the selected competitors rank for but the site does not track, use get_competitor_keyword_gap.')]
#[IsReadOnly]
class GetCompetitors extends SeoTool
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
        $domain->load('keywords.latestCheck');

        $competitors = $domain->competitors()->get();
        $tracked = $competitors->where('status', DomainCompetitor::TRACKED)->pluck('competitor_domain')->values()->all();
        $ignored = $competitors->where('status', DomainCompetitor::IGNORED)->pluck('competitor_domain')->values()->all();

        $serp = (new CompetitorAnalyzer())->frequentCompetitors($domain->domain, $domain->keywords, $ignored);
        $share = LlmShareOfModel::forDomain($domain, $serp, $tracked);

        return Response::json([
            'domain' => $domain->domain,
            'selected_competitors' => $tracked,
            'ignored_competitors' => $ignored,
            'serp' => [
                'keywords_with_serp_data' => $serp['tracked_keyword_count'],
                'competitors' => array_map(fn (array $c) => [
                    'domain' => $c['domain'],
                    'selected' => in_array(DomainHelper::normalize($c['domain']), $tracked, true),
                    'shared_keywords' => $c['keyword_count'],
                    'average_position' => $c['average_position'],
                    'best_position' => $c['best_position'],
                    'keywords' => $c['keywords'],
                ], $serp['competitors']),
            ],
            'share_of_model' => [
                'period_days' => LlmShareOfModel::DAYS,
                'answers' => $share['answers'],
                'answers_by_assistant' => collect($share['answered'])
                    ->mapWithKeys(fn (int $count, string $provider) => [AiAssistant::label($provider) => $count])
                    ->all(),
                'rows' => array_map(fn (array $row) => [
                    'domain' => $row['domain'],
                    'is_own_site' => $row['own'],
                    'mention_rate_pct' => $row['rate'],
                    'mention_rate_pct_by_assistant' => collect($row['by_provider'])
                        ->mapWithKeys(fn (int $rate, string $provider) => [AiAssistant::label($provider) => $rate])
                        ->all(),
                ], $share['rows']),
            ],
            'url' => route('domains.show', $domain) . '#tab-competitors',
        ]);
    }
}
