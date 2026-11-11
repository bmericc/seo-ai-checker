<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Domain;
use App\Models\User;
use App\Support\DomainHealth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_domains')]
#[Description('Lists the domains you can access - your own plus ones other members shared with you (admins see every member\'s domains) - with your access level (owner/write/read), health score, keyword count and AI Overview citation rate.')]
#[IsReadOnly]
class ListDomains extends SeoTool
{
    public function handle(Request $request): Response
    {
        $user = $this->authorizedUser();

        $domains = $this->visibleDomains($user)
            ->with(['user', 'fact', 'latestDomainCheck', 'keywords.latestCheck', 'members' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('domain')
            ->get();

        return Response::json([
            'count' => $domains->count(),
            'domains' => $domains->map(fn (Domain $domain) => $this->summary($domain, $user))->values(),
        ]);
    }

    private function summary(Domain $domain, User $user): array
    {
        $withOwner = $user->is_admin || $domain->user_id !== $user->id;

        $health = DomainHealth::for($domain);
        $ai = $domain->aiVisibilitySnapshot();

        return array_filter([
            'id' => $domain->id,
            'domain' => $domain->domain,
            'owner' => $withOwner ? $domain->user?->email : null,
            // owner | write | read - paylasilan domainlerde "read" ise yazma tool'lari calismaz.
            'access' => $domain->accessLevelFor($user),
            'health_score' => $health->score,
            'health_summary' => $health->summary(),
            'keyword_count' => $domain->keywords->count(),
            'ai_overview_citation_rate' => $ai['score'],
            'last_site_check_at' => self::iso($domain->latestDomainCheck?->created_at),
            'url' => route('domains.show', $domain),
        ], fn ($value) => $value !== null);
    }
}
