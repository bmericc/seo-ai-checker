<?php

declare(strict_types=1);

namespace App\Mobile;

use App\Models\Check;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\PromptExploration;
use App\Models\User;
use App\Support\AiAssistant;
use App\Support\DomainHealth;

/**
 * Mobil uygulamanin domain ekrani. Ekran sunucuda bolumlerin (section)
 * sirali listesi olarak kurulur; uygulama her bolum tipini bir kez cizer
 * ("score", "issues", "metrics", "assistants", "table", "notice").
 * Tanimadigi bir tipi baslik ve "Web'de aç" baglantisiyla gosterir, bu
 * yuzden yeni bolum eklemek eski uygulama surumlerini bozmaz.
 *
 * Veriler web panelindeki ve MCP'deki ayni kaynaklardan okunur
 * (DomainHealth, Domain::aiVisibilitySnapshot, son kontroller).
 */
final class DomainScreen
{
    private const ISSUE_LIMIT = 5;

    private const KEYWORD_LIMIT = 10;

    /**
     * Domain listesindeki tek satir.
     *
     * @return array<string, mixed>
     */
    public static function summary(Domain $domain, User $user): array
    {
        $health = DomainHealth::for($domain);
        $highIssues = collect($health->issues)->where('severity', DomainHealth::SEVERITY_HIGH)->count();

        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'access' => $domain->accessLevelFor($user),
            'owner' => $domain->user_id !== $user->id ? $domain->user?->email : null,
            'score' => $health->score,
            'level' => self::level($health->score),
            'keyword_count' => $domain->keywords->count(),
            'issue_count' => count($health->issues),
            'high_issue_count' => $highIssues,
            'top_issue' => $health->issues[0]['title'] ?? null,
            'last_check_at' => $domain->latestDomainCheck?->created_at?->toIso8601String(),
            'web_url' => route('domains.show', $domain),
        ];
    }

    /**
     * Web'deki domain sayfasiyla ayni sekmeler (bkz. domains/show.blade.php).
     * "pages" yalnizca site haritasinda sayfa varsa listelenir.
     */
    public const TABS = ['overview', 'keywords', 'health', 'ai', 'pages', 'indexnow', 'competitors', 'backlinks', 'history', 'info'];

    /**
     * @return array<string, mixed>
     */
    public static function for(Domain $domain, User $user, string $tab = 'overview'): array
    {
        $domain->load(['fact', 'latestDomainCheck', 'keywords.latestCheck']);
        $health = DomainHealth::for($domain);
        $tabs = self::tabs($domain, $health);
        $tab = in_array($tab, array_column($tabs, 'key'), true) ? $tab : 'overview';
        $webUrl = route('domains.show', $domain).($tab === 'overview' ? '' : '#tab-'.$tab);

        $sections = match ($tab) {
            'keywords' => DomainTabs::keywords($domain, $webUrl),
            'health' => DomainTabs::health($domain, $webUrl),
            'ai' => DomainTabs::ai($domain, $webUrl),
            'pages' => DomainTabs::pages($domain, $webUrl),
            'indexnow' => DomainTabs::indexNow($domain, $webUrl),
            'competitors' => DomainTabs::competitors($domain, $webUrl),
            'backlinks' => DomainTabs::backlinks($domain, $webUrl),
            'history' => DomainTabs::history($domain, $webUrl),
            'info' => DomainTabs::info($domain, $webUrl),
            default => array_values(array_filter([
                self::scoreSection($domain, $health, $webUrl),
                self::issuesSection($health, $webUrl),
                self::metricsSection($domain),
                self::assistantsSection($domain, $webUrl),
                self::keywordsSection($domain, $webUrl),
            ])),
        };

        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'access' => $domain->accessLevelFor($user),
            'site_url' => $domain->rootUrl(),
            'web_url' => $webUrl,
            'tabs' => $tabs,
            'tab' => $tab,
            'sections' => array_values($sections),
        ];
    }

    /**
     * @return list<array{key: string, label: string, badge: ?int}>
     */
    private static function tabs(Domain $domain, DomainHealth $health): array
    {
        $labels = [
            'overview' => __('Genel bakış'),
            'keywords' => __('Anahtar kelimeler'),
            'health' => __('Site sağlığı'),
            'ai' => __('Yapay zekâ görünürlüğü'),
            'pages' => __('Sayfalar'),
            'indexnow' => __('Hızlı indeksleme'),
            'competitors' => __('Rakipler'),
            'backlinks' => __('Backlink'),
            'history' => __('Geçmiş'),
            'info' => __('Domain bilgisi'),
        ];
        $badges = ['keywords' => $domain->keywords->count(), 'health' => count($health->issues) ?: null];
        $hasPages = $domain->sitemapUrls()->exists();

        return collect(self::TABS)
            ->reject(fn (string $key) => $key === 'pages' && !$hasPages)
            ->map(fn (string $key) => ['key' => $key, 'label' => $labels[$key], 'badge' => $badges[$key] ?? null])
            ->values()
            ->all();
    }

    private static function scoreSection(Domain $domain, DomainHealth $health, string $webUrl): array
    {
        if (!$health->hasData) {
            return [
                'type' => 'notice',
                'title' => __('Site sağlığı'),
                'data' => [
                    'level' => 'info',
                    'text' => __('Bu site henüz kontrol edilmedi. Kontrolü web panelinden başlatabilirsiniz.'),
                ],
                'web_url' => $webUrl,
            ];
        }

        return [
            'type' => 'score',
            'title' => __('Site sağlığı'),
            'data' => [
                'value' => $health->score,
                'max' => 100,
                'level' => self::level($health->score),
                'caption' => $health->summary(),
                'checked_at' => $domain->latestDomainCheck?->created_at?->toIso8601String(),
            ],
        ];
    }

    private static function issuesSection(DomainHealth $health, string $webUrl): ?array
    {
        if ($health->issues === []) {
            return null;
        }

        return [
            'type' => 'issues',
            'title' => __('Öncelikli sorunlar'),
            'data' => [
                'items' => collect($health->issues)
                    ->take(self::ISSUE_LIMIT)
                    ->map(fn (array $issue) => [
                        'severity' => $issue['severity'],
                        'title' => $issue['title'],
                        'description' => $issue['description'],
                        'fix' => $issue['fix'],
                    ])->values()->all(),
                'more' => max(0, count($health->issues) - self::ISSUE_LIMIT),
            ],
            'web_url' => $webUrl,
        ];
    }

    private static function metricsSection(Domain $domain): ?array
    {
        $latest = $domain->keywords->map->latestCheck->filter();
        $positions = $latest->pluck('target_position')->filter(fn ($p) => $p !== null);
        $ai = $domain->aiVisibilitySnapshot();
        $clicks = $domain->latestDomainCheck?->gsc['clicks'] ?? null;

        $items = array_values(array_filter([
            [
                'label' => __('Kelime'),
                'value' => (string) $domain->keywords->count(),
            ],
            $positions->isNotEmpty() ? [
                'label' => __('Ort. Google sırası'),
                'value' => number_format((float) $positions->avg(), 1, app()->getLocale() === 'tr' ? ',' : '.', ''),
            ] : null,
            $ai['checked_count'] > 0 ? [
                'label' => __('AI Overview atfı'),
                'value' => $ai['cited_count'].' / '.$ai['present_count'],
                'caption' => $ai['present_count'] > 0 ? null : __('AI Overview çıkmadı'),
            ] : null,
            is_numeric($clicks) ? [
                'label' => __('Search Console tıklama'),
                'value' => number_format((float) $clicks, 0, ',', '.'),
            ] : null,
        ]));

        return count($items) > 1 ? [
            'type' => 'metrics',
            'title' => __('Özet'),
            'data' => ['items' => $items],
        ] : null;
    }

    private static function assistantsSection(Domain $domain, string $webUrl): ?array
    {
        /** @var ?PromptExploration $exploration */
        $exploration = $domain->promptExplorations()
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();

        if ($exploration === null) {
            return null;
        }

        $items = collect($exploration->results ?? [])
            ->filter(fn ($result) => is_array($result))
            ->map(fn (array $result, string $provider) => [
                'assistant' => AiAssistant::label($provider),
                'status' => ($result['error'] ?? null) !== null ? 'error' : (($result['present'] ?? false) ? 'mentioned' : 'absent'),
            ])->values()->all();

        if ($items === []) {
            return null;
        }

        return [
            'type' => 'assistants',
            'title' => __('Yapay zekâ asistanları'),
            'data' => [
                'prompt' => $exploration->prompt,
                'checked_at' => $exploration->completed_at?->toIso8601String(),
                'items' => $items,
            ],
            'web_url' => $webUrl,
        ];
    }

    private static function keywordsSection(Domain $domain, string $webUrl): ?array
    {
        if ($domain->keywords->isEmpty()) {
            return [
                'type' => 'notice',
                'title' => __('Kelimeler'),
                'data' => [
                    'level' => 'info',
                    'text' => __('Henüz takip edilen kelime yok. Kelimeleri web panelinden ekleyebilirsiniz.'),
                ],
                'web_url' => $webUrl,
            ];
        }

        // Once siralanan kelimeler (en iyi sira ustte), sonra siralanmayanlar.
        $rows = $domain->keywords
            ->sortBy(fn (Keyword $k) => [$k->latestCheck?->target_position === null ? 1 : 0, $k->latestCheck?->target_position ?? 0, $k->keyword])
            ->take(self::KEYWORD_LIMIT)
            ->map(fn (Keyword $keyword) => self::keywordRow($keyword))
            ->values()
            ->all();

        return [
            'type' => 'table',
            'title' => __('Kelimeler'),
            'data' => [
                'rows' => $rows,
                'total' => $domain->keywords->count(),
                'more_label' => $domain->keywords->count() > self::KEYWORD_LIMIT
                    ? __('Tüm kelimeler (:count)', ['count' => $domain->keywords->count()])
                    : null,
            ],
            'web_url' => $webUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function keywordRow(Keyword $keyword): array
    {
        /** @var ?Check $check */
        $check = $keyword->latestCheck;

        $badge = match (true) {
            $check?->ai_overview_target_cited => ['label' => __('AIO kaynak'), 'tone' => 'good'],
            $check?->ai_overview_present => ['label' => __('AIO var'), 'tone' => 'warn'],
            default => null,
        };

        return [
            'title' => $keyword->keyword,
            'value' => $check?->target_position !== null ? (string) $check->target_position : '—',
            'badges' => $badge !== null ? [$badge] : [],
        ];
    }

    private static function level(?int $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= 80 => 'good',
            $score >= 60 => 'warn',
            default => 'bad',
        };
    }
}
