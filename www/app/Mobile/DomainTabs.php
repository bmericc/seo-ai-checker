<?php

declare(strict_types=1);

namespace App\Mobile;

use App\Models\Check;
use App\Models\CompetitorBlacklist;
use App\Models\Domain;
use App\Models\DomainCompetitor;
use App\Models\IndexNowSubmission;
use App\Models\Keyword;
use App\Models\PromptExploration;
use App\Models\SitemapUrl;
use App\Services\Analytics\AiOverviewVolatility;
use App\Services\Analytics\CompetitorAnalyzer;
use App\Services\Analytics\CompetitorKeywordGap;
use App\Services\Analytics\LlmShareOfModel;
use App\Services\Analytics\ScoreHistoryBuilder;
use App\Services\Drift\DomainCheckDrift;
use App\Support\AiAssistant;
use App\Support\BacklinkOverview;
use App\Support\DomainHealth;

/**
 * Web'deki domain sayfasinin sekmeleri (Genel bakis disinda) mobil bolum
 * listesi olarak. Her sekme web'deki ayni servisleri kullanir (bkz.
 * DomainController::show); yazma islemleri (kontrol baslat, kelime ekle,
 * rakip sec...) uygulamada yok, bolumlerin "Web'de aç" baglantisi web
 * panelindeki ayni sekmeyi acar.
 */
final class DomainTabs
{
    private const KEYWORD_LIMIT = 200;

    private const SITEMAP_LIMIT = 100;

    private const HISTORY_POINTS = 90;

    /**
     * @return list<array<string, mixed>>
     */
    public static function keywords(Domain $domain, string $webUrl): array
    {
        $domain->load([
            'keywords' => fn ($q) => $q->orderBy('keyword'),
            'keywords.latestCheck',
            'keywords.aiChecks' => fn ($q) => $q->limit(10),
        ]);

        if ($domain->keywords->isEmpty()) {
            return [Sections::notice(__('Anahtar kelimeler'), __('Henüz takip edilen kelime yok. Kelimeleri web panelinden ekleyebilirsiniz.'), $webUrl)];
        }

        $rows = $domain->keywords->take(self::KEYWORD_LIMIT)->map(function (Keyword $keyword) {
            /** @var ?Check $check */
            $check = $keyword->latestCheck;
            $badges = [];

            if ($check?->ai_overview_target_cited) {
                $badges[] = Sections::badge(__('AIO kaynak'), 'good');
            } elseif ($check?->ai_overview_present) {
                $badges[] = Sections::badge(__('AIO var'), 'warn');
            }
            if ($check?->mobile_target_position !== null) {
                $badges[] = Sections::badge(__('Mobil :position', ['position' => $check->mobile_target_position]), 'neutral');
            }
            foreach ($keyword->latestAiResultsByProvider() as $provider => $entry) {
                if (($entry['result']['error'] ?? null) === null) {
                    $badges[] = Sections::badge(AiAssistant::label($provider), ($entry['result']['present'] ?? false) ? 'good' : 'neutral');
                }
            }
            if ($check?->blocked) {
                $badges[] = Sections::badge(__('engellendi'), 'warn');
            }

            $meta = array_filter([
                $keyword->search_volume !== null ? __('Hacim :volume', ['volume' => Sections::number($keyword->search_volume)]) : null,
                $keyword->keyword_difficulty !== null ? 'KD '.$keyword->keyword_difficulty : null,
                $check?->created_at !== null ? __('son kontrol :ago', ['ago' => $check->created_at->diffForHumans()]) : __('Henüz kontrol edilmedi'),
            ]);

            return [
                'title' => $keyword->keyword,
                'subtitle' => implode(' · ', $meta),
                'value' => $check?->target_position !== null ? (string) $check->target_position : '—',
                'badges' => $badges,
            ];
        })->values()->all();

        return [[
            'type' => 'table',
            'title' => __('Anahtar kelimeler'),
            'data' => [
                'caption' => __('Sağdaki sayı masaüstü Google sırası.'),
                'rows' => $rows,
                'total' => $domain->keywords->count(),
                'more_label' => $domain->keywords->count() > self::KEYWORD_LIMIT ? __('Tüm kelimeler (:count)', ['count' => $domain->keywords->count()]) : null,
            ],
            'web_url' => $webUrl,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function health(Domain $domain, string $webUrl): array
    {
        $domain->load(['fact', 'latestDomainCheck']);
        $health = DomainHealth::for($domain);

        if (!$health->hasData) {
            return [Sections::notice(__('Site sağlığı'), __('Bu site henüz kontrol edilmedi. Kontrolü web panelinden başlatabilirsiniz.'), $webUrl)];
        }

        $sections = [[
            'type' => 'checks',
            'title' => __('Kontroller'),
            'data' => [
                'items' => array_map(fn (array $check) => [
                    'label' => $check['label'],
                    'status' => $check['status'],
                    'detail' => $check['detail'],
                ], $health->checks),
            ],
        ]];

        if ($health->issues !== []) {
            $sections[] = [
                'type' => 'issues',
                'title' => __('Sorunlar ve çözümleri'),
                'data' => ['items' => array_map(fn (array $issue) => [
                    'severity' => $issue['severity'],
                    'title' => $issue['title'],
                    'description' => $issue['description'],
                    'fix' => $issue['fix'],
                ], $health->issues), 'more' => 0],
            ];
        }

        $crux = $domain->fact?->crux ?? [];
        if (($crux['found'] ?? false) && ($crux['metrics'] ?? []) !== []) {
            $sections[] = [
                'type' => 'metrics',
                'title' => __('Gerçek kullanıcı hızı (CrUX, 28 gün)'),
                'data' => ['items' => collect($crux['metrics'])->map(fn (array $metric) => [
                    'label' => $metric['label'],
                    'value' => self::cruxValue($metric['label'], $metric['p75']),
                    'tone' => match ($metric['rating'] ?? null) {
                        'good' => 'good',
                        'needs_improvement' => 'warn',
                        'poor' => 'bad',
                        default => null,
                    },
                ])->values()->all()],
            ];
        }

        $gsc = $domain->latestDomainCheck?->gsc ?? [];
        if (is_numeric($gsc['clicks'] ?? null)) {
            $sections[] = [
                'type' => 'metrics',
                'title' => __('Search Console (son 28 gün)'),
                'data' => ['items' => array_values(array_filter([
                    ['label' => __('Tıklama'), 'value' => Sections::number($gsc['clicks'])],
                    is_numeric($gsc['impressions'] ?? null) ? ['label' => __('Gösterim'), 'value' => Sections::number($gsc['impressions'])] : null,
                    is_numeric($gsc['ctr'] ?? null) ? ['label' => 'CTR', 'value' => Sections::decimal($gsc['ctr'] * 100, 1).'%'] : null,
                    is_numeric($gsc['average_position'] ?? null) ? ['label' => __('Ort. sıra'), 'value' => Sections::decimal($gsc['average_position'], 1)] : null,
                ]))],
            ];
        }

        $ga4 = $domain->latestDomainCheck?->ga4 ?? [];
        if (is_numeric($ga4['total_sessions'] ?? null)) {
            $sections[] = [
                'type' => 'metrics',
                'title' => __('Google Analytics (son 28 gün)'),
                'data' => ['items' => array_values(array_filter([
                    ['label' => __('Oturum'), 'value' => Sections::number($ga4['total_sessions'])],
                    is_numeric($ga4['organic_sessions'] ?? null) ? ['label' => __('Organik oturum'), 'value' => Sections::number($ga4['organic_sessions'])] : null,
                    is_numeric($ga4['active_users'] ?? null) ? ['label' => __('Aktif kullanıcı'), 'value' => Sections::number($ga4['active_users'])] : null,
                ]))],
            ];
        }

        $recent = $domain->domainChecks()->limit(2)->get();
        if ($recent->count() === 2) {
            $drift = (new DomainCheckDrift())->diff($recent[0], $recent[1]);
            if ($drift !== []) {
                $sections[] = [
                    'type' => 'list',
                    'title' => __('Son iki kontrol arasındaki değişiklikler'),
                    'data' => ['items' => array_map(fn (string $text) => ['title' => $text], $drift)],
                ];
            }
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function ai(Domain $domain, string $webUrl): array
    {
        $domain->load(['keywords' => fn ($q) => $q->orderBy('keyword'), 'keywords.latestCheck']);
        $stability = AiOverviewVolatility::forKeywords($domain->keywords);
        $sections = [];

        if ($stability['summary']['measured_keywords'] > 0) {
            $summary = $stability['summary'];
            $sections[] = [
                'type' => 'metrics',
                'title' => __('AI Overview (son 10 kontrol)'),
                'data' => ['items' => array_values(array_filter([
                    ['label' => __('Ölçülen kelime'), 'value' => (string) $summary['measured_keywords']],
                    $summary['aio_rate'] !== null ? ['label' => __('AI Overview çıkma oranı'), 'value' => '%'.$summary['aio_rate']] : null,
                    $summary['citation_rate'] !== null ? ['label' => __('Kaynak gösterilme oranı'), 'value' => '%'.$summary['citation_rate']] : null,
                    ['label' => __('Değişken kelime'), 'value' => (string) $summary['volatile_keywords'], 'tone' => $summary['volatile_keywords'] > 0 ? 'warn' : null],
                ]))],
            ];

            $sections[] = [
                'type' => 'table',
                'title' => __('Kelime bazında AI Overview'),
                'data' => ['rows' => collect($stability['keywords'])
                    ->filter(fn (array $row) => $row['checks'] > 0)
                    ->map(fn (array $row) => [
                        'title' => $row['keyword'],
                        'subtitle' => implode(' · ', array_filter([
                            $row['aio_rate'] !== null ? __('AIO %:rate', ['rate' => $row['aio_rate']]) : null,
                            $row['citation_rate'] !== null ? __('kaynak %:rate', ['rate' => $row['citation_rate']]) : null,
                            __(':count kontrol', ['count' => $row['checks']]),
                        ])),
                        'value' => $row['citation_rate'] !== null ? '%'.$row['citation_rate'] : '—',
                        'badges' => $row['level'] === AiOverviewVolatility::LEVEL_HIGH ? [Sections::badge(__('değişken'), 'warn')] : [],
                    ])->values()->all()],
                'web_url' => $webUrl,
            ];
        }

        $competitors = $domain->competitors()->get();
        $analysis = (new CompetitorAnalyzer())->frequentCompetitors(
            $domain->domain,
            $domain->keywords,
            $competitors->where('status', DomainCompetitor::IGNORED)->pluck('competitor_domain')->all(),
            CompetitorBlacklist::domains(),
        );
        $share = LlmShareOfModel::forDomain($domain, $analysis, $competitors->where('status', DomainCompetitor::TRACKED)->pluck('competitor_domain')->all());

        if (($share['answers'] ?? 0) > 0 && ($share['rows'] ?? []) !== []) {
            $sections[] = [
                'type' => 'table',
                'title' => __('Asistanlarda anılma payı (Share of Model)'),
                'data' => [
                    'caption' => __(':count yanıta göre', ['count' => $share['answers']]),
                    'rows' => collect($share['rows'])->take(15)->map(fn (array $row) => [
                        'title' => $row['domain'],
                        'subtitle' => collect($row['by_provider'])
                            ->filter(fn ($rate) => $rate !== null)
                            ->map(fn ($rate, $provider) => AiAssistant::label($provider).' %'.$rate)
                            ->implode(' · '),
                        'value' => '%'.$row['rate'],
                        'badges' => $row['own'] ? [Sections::badge(__('siz'), 'info')] : [],
                    ])->values()->all(),
                ],
                'web_url' => $webUrl,
            ];
        }

        $explorations = $domain->promptExplorations()->whereNotNull('completed_at')->latest('completed_at')->limit(10)->get();
        if ($explorations->isNotEmpty()) {
            $sections[] = [
                'type' => 'list',
                'title' => __('Son yapay zekâ kontrolleri'),
                'data' => ['items' => $explorations->map(function (PromptExploration $exploration) {
                    $results = collect($exploration->results ?? [])->filter(fn ($r) => is_array($r) && ($r['error'] ?? null) === null);
                    $mentioned = $results->filter(fn (array $r) => $r['present'] ?? false)->keys()->map(fn ($p) => AiAssistant::label($p));

                    return [
                        'title' => $exploration->prompt,
                        'subtitle' => $mentioned->isNotEmpty()
                            ? __('Anan: :assistants', ['assistants' => $mentioned->implode(', ')])
                            : __('Hiçbir asistan anmadı'),
                        'meta' => $exploration->completed_at?->diffForHumans(),
                        'tone' => $mentioned->isNotEmpty() ? 'good' : 'neutral',
                    ];
                })->values()->all()],
                'web_url' => $webUrl,
            ];
        }

        return $sections !== [] ? $sections : [Sections::notice(__('Yapay zekâ görünürlüğü'), __('Henüz yapay zekâ görünürlüğü verisi yok. Kelimeler kontrol edildikçe burada görünür.'), $webUrl)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pages(Domain $domain, string $webUrl): array
    {
        $active = $domain->sitemapUrls()->whereNull('removed_at')->count();
        $removed = $domain->sitemapUrls()->whereNotNull('removed_at')->count();
        $urls = $domain->sitemapUrls()
            ->orderByRaw('removed_at IS NULL')
            ->orderByDesc('removed_at')
            ->orderByDesc('last_seen_at')
            ->limit(self::SITEMAP_LIMIT)
            ->get();
        $root = rtrim($domain->rootUrl(), '/');

        return [
            [
                'type' => 'metrics',
                'title' => __('Site haritasındaki sayfalar'),
                'data' => ['items' => [
                    ['label' => __('Aktif'), 'value' => Sections::number($active)],
                    ['label' => __('Kaldırılan'), 'value' => Sections::number($removed), 'tone' => $removed > 0 ? 'warn' : null],
                ]],
            ],
            [
                'type' => 'table',
                'title' => __('Sayfalar'),
                'data' => [
                    'rows' => $urls->map(fn (SitemapUrl $url) => [
                        'title' => str_starts_with($url->url, $root) ? (substr($url->url, strlen($root)) ?: '/') : $url->url,
                        'subtitle' => $url->removed_at !== null
                            ? __('kaldırıldı :ago', ['ago' => $url->removed_at->diffForHumans()])
                            : __('son görülme :ago', ['ago' => $url->last_seen_at?->diffForHumans() ?? '—']),
                        'value' => $url->lighthouse_performance !== null ? (string) $url->lighthouse_performance : '',
                        'badges' => $url->removed_at !== null ? [Sections::badge(__('kaldırıldı'), 'bad')] : [],
                    ])->values()->all(),
                    'more_label' => $active + $removed > self::SITEMAP_LIMIT ? __('Tüm sayfalar (:count)', ['count' => $active + $removed]) : null,
                ],
                'web_url' => $webUrl,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function indexNow(Domain $domain, string $webUrl): array
    {
        $sections = [[
            'type' => 'kv',
            'title' => __('IndexNow ile hızlı indeksleme'),
            'data' => ['items' => [
                ['label' => __('Anahtar'), 'value' => $domain->indexnow_key !== null ? __('Oluşturuldu') : __('Yok')],
                ['label' => __('Doğrulama'), 'value' => $domain->indexnow_verified_at !== null
                    ? __('Doğrulandı (:date)', ['date' => $domain->indexnow_verified_at->isoFormat('LL')])
                    : __('Henüz doğrulanmadı'), 'tone' => $domain->indexnow_verified_at !== null ? 'good' : 'warn'],
            ]],
            'web_url' => $webUrl,
        ]];

        $submissions = $domain->indexNowSubmissions()->limit(10)->get();
        $sections[] = $submissions->isEmpty()
            ? Sections::notice(__('Son bildirimler'), __('Henüz bildirim yapılmadı.'))
            : [
                'type' => 'list',
                'title' => __('Son bildirimler'),
                'data' => ['items' => $submissions->map(fn (IndexNowSubmission $s) => [
                    'title' => trans_choice(':count URL|:count URL', $s->url_count, ['count' => $s->url_count]),
                    'subtitle' => implode(' · ', array_filter([
                        $s->source === 'sitemap' ? __('site haritası') : __('elle'),
                        $s->status_code !== null ? 'HTTP '.$s->status_code : null,
                        $s->ok ? null : $s->message,
                    ])),
                    'meta' => $s->created_at?->diffForHumans(),
                    'tone' => $s->ok ? 'good' : 'bad',
                ])->values()->all()],
            ];

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function competitors(Domain $domain, string $webUrl): array
    {
        $domain->load(['keywords.latestCheck']);
        $competitors = $domain->competitors()->get();
        $tracked = $competitors->where('status', DomainCompetitor::TRACKED)->values();
        $ignored = $competitors->where('status', DomainCompetitor::IGNORED)->pluck('competitor_domain')->all();

        $analysis = (new CompetitorAnalyzer())->frequentCompetitors($domain->domain, $domain->keywords, $ignored, CompetitorBlacklist::domains());
        $trackedNames = $tracked->pluck('competitor_domain')->all();

        if ($analysis['competitors'] === [] && $tracked->isEmpty()) {
            return [Sections::notice(__('Rakipler'), __('Rakipler, kelimeleriniz kontrol edildikçe Google sonuçlarından çıkarılır. Henüz yeterli veri yok.'), $webUrl)];
        }

        $sections = [[
            'type' => 'table',
            'title' => __('Sık çıkan rakipler'),
            'data' => [
                'caption' => __(':count kelimenin sonuçlarına göre', ['count' => $analysis['tracked_keyword_count']]),
                'rows' => collect($analysis['competitors'])->take(30)->map(fn (array $c) => [
                    'title' => $c['domain'],
                    'subtitle' => __(':count kelime · ort. sıra :avg', ['count' => $c['keyword_count'], 'avg' => Sections::decimal($c['average_position'], 1)]),
                    'value' => (string) $c['best_position'],
                    'badges' => in_array($c['domain'], $trackedNames, true) ? [Sections::badge(__('takipte'), 'info')] : [],
                ])->values()->all(),
            ],
            'web_url' => $webUrl,
        ]];

        $gap = CompetitorKeywordGap::forDomain($domain, $tracked);
        if ($gap !== []) {
            $sections[] = [
                'type' => 'table',
                'title' => __('Rakiplerin sıralanıp sizin takip etmediğiniz kelimeler'),
                'data' => ['rows' => collect($gap)->take(30)->map(fn (array $row) => [
                    'title' => $row['keyword'],
                    'subtitle' => implode(' · ', array_filter([
                        __(':count rakip', ['count' => $row['competitor_count']]),
                        $row['search_volume'] !== null ? __('Hacim :volume', ['volume' => Sections::number($row['search_volume'])]) : null,
                    ])),
                    'value' => $row['best_position'] !== null ? (string) $row['best_position'] : '—',
                    'badges' => [],
                ])->values()->all()],
                'web_url' => $webUrl,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function backlinks(Domain $domain, string $webUrl): array
    {
        $overview = BacklinkOverview::forDomain($domain);
        $snapshot = $overview['snapshot'];
        $previous = $overview['previous'];
        $sections = [];

        if ($snapshot !== null) {
            $sections[] = [
                'type' => 'metrics',
                'title' => __('Backlink özeti'),
                'data' => ['items' => array_values(array_filter([
                    ['label' => __('Backlink'), 'value' => Sections::number($snapshot->backlinks), 'caption' => Sections::delta($snapshot->backlinks, $previous?->backlinks)],
                    ['label' => __('Referans domain'), 'value' => Sections::number($snapshot->referring_domains), 'caption' => Sections::delta($snapshot->referring_domains, $previous?->referring_domains)],
                    $snapshot->rank !== null ? ['label' => __('Rank'), 'value' => (string) $snapshot->rank] : null,
                    $snapshot->spam_score !== null ? ['label' => __('Spam skoru'), 'value' => (string) $snapshot->spam_score, 'tone' => $snapshot->spam_score >= 30 ? 'warn' : null] : null,
                ])), 'caption' => $snapshot->fetched_at !== null ? __('güncellendi :ago', ['ago' => $snapshot->fetched_at->diffForHumans()]) : null],
            ];
        } elseif ($overview['bingDomainCount'] !== null) {
            $sections[] = [
                'type' => 'metrics',
                'title' => __('Backlink özeti'),
                'data' => ['items' => [['label' => __('Referans domain (Bing)'), 'value' => Sections::number($overview['bingDomainCount'])]]],
            ];
        }

        $rows = fn (array $list, int $limit) => collect($list)->take($limit)->map(fn (array $row) => [
            'title' => $row['domain'],
            'subtitle' => implode(' · ', array_filter([
                $row['backlinks'] !== null ? __(':count link', ['count' => Sections::number($row['backlinks'])]) : null,
                $row['nofollow'] ? 'nofollow' : null,
                $row['anchor'] ? '“'.$row['anchor'].'”' : null,
            ])),
            'value' => $row['rank'] !== null ? (string) $row['rank'] : '',
            'badges' => [],
        ])->values()->all();

        if ($overview['merged']['domains'] !== []) {
            $sections[] = ['type' => 'table', 'title' => __('Öne çıkan referans domainler'), 'data' => ['rows' => $rows($overview['merged']['domains'], 30)], 'web_url' => $webUrl];
        }
        if ($overview['merged']['new'] !== []) {
            $sections[] = ['type' => 'table', 'title' => __('Yeni kazanılanlar'), 'data' => ['rows' => $rows($overview['merged']['new'], 15)], 'web_url' => $webUrl];
        }
        if ($overview['merged']['lost'] !== []) {
            $sections[] = ['type' => 'table', 'title' => __('Kaybedilenler'), 'data' => ['rows' => $rows($overview['merged']['lost'], 15)], 'web_url' => $webUrl];
        }

        return $sections !== [] ? $sections : [Sections::notice(__('Backlink'), __('Henüz backlink verisi yok. Backlink verisini web panelinden getirebilirsiniz.'), $webUrl)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function history(Domain $domain, string $webUrl): array
    {
        $builder = new ScoreHistoryBuilder();
        $keywordChecks = Check::query()
            ->whereIn('keyword_id', $domain->keywords()->pluck('id'))
            ->select(['id', 'created_at', 'ai_overview_present', 'ai_overview_target_cited', 'lighthouse_performance', 'lighthouse_seo', 'lighthouse_accessibility', 'lighthouse_best_practices', 'target_position'])
            ->orderBy('created_at')->orderBy('id')
            ->get();
        $scores = $builder->groupedByDay($keywordChecks);
        $site = $builder->domainCheckHistory(
            $domain->domainChecks()->reorder()->select(['id', 'domain_id', 'created_at', 'crux', 'gsc', 'ga4'])->orderBy('created_at')->orderBy('id')->get()
        );

        $sections = array_values(array_filter([
            Sections::chart(__('Ortalama Google sırası'), $scores['labels'], [[__('Ort. sıra'), $scores['average_position']]], self::HISTORY_POINTS, invert: true),
            Sections::chart(__('AI Overview görünürlüğü'), $scores['labels'], [[__('Kaynak gösterilme %'), $scores['ai_visibility']]], self::HISTORY_POINTS, unit: '%'),
            Sections::chart('Lighthouse', $scores['labels'], [
                [__('Performans'), $scores['lighthouse_performance']],
                ['SEO', $scores['lighthouse_seo']],
                [__('Erişilebilirlik'), $scores['lighthouse_accessibility']],
                [__('En iyi uygulamalar'), $scores['lighthouse_best_practices']],
            ], self::HISTORY_POINTS),
            Sections::chart(__('Search Console tıklama'), $site['labels'], [[__('Tıklama'), $site['gsc_clicks']]], self::HISTORY_POINTS),
            Sections::chart(__('Search Console gösterim'), $site['labels'], [[__('Gösterim'), $site['gsc_impressions']]], self::HISTORY_POINTS),
            Sections::chart(__('Google Analytics oturum'), $site['labels'], [
                [__('Toplam'), $site['ga4_total_sessions']],
                [__('Organik'), $site['ga4_organic_sessions']],
            ], self::HISTORY_POINTS),
            Sections::chart(__('Gerçek kullanıcı hızı (LCP, ms)'), $site['labels'], [['LCP', $site['crux_lcp']]], self::HISTORY_POINTS, invert: true),
        ]));

        return $sections !== [] ? $sections : [Sections::notice(__('Geçmiş'), __('Grafikler için en az iki günlük kontrol verisi gerekiyor.'), $webUrl)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function info(Domain $domain, string $webUrl): array
    {
        $domain->load(['fact', 'user', 'members.user']);
        $fact = $domain->fact;
        $age = $domain->whoisAgeInYears();

        $sections = [[
            'type' => 'kv',
            'title' => __('Domain bilgisi'),
            'data' => ['items' => array_values(array_filter([
                ['label' => __('Domain'), 'value' => $domain->domain],
                ['label' => __('Sahip'), 'value' => $domain->user?->email ?? '—'],
                ['label' => __('Eklenme'), 'value' => $domain->created_at?->isoFormat('LL') ?? '—'],
                $age !== null ? ['label' => __('Domain Yaşı'), 'value' => trans_choice(':count yıl|:count yıl', $age, ['count' => $age])] : null,
                $fact?->whois_registered_at ? ['label' => __('Kayıt tarihi'), 'value' => $fact->whois_registered_at->isoFormat('LL')] : null,
                $fact?->whois_expires_at ? ['label' => __('Kayıt Süresi Sonu'), 'value' => $fact->whois_expires_at->isoFormat('LL'), 'tone' => $fact->whois_expires_at->isBefore(now()->addDays(30)) ? 'warn' : null] : null,
                $fact?->whois_registrar ? ['label' => __('Registrar'), 'value' => $fact->whois_registrar] : null,
                $fact?->whois_checked_at ? ['label' => __('WHOIS kontrolü'), 'value' => $fact->whois_checked_at->diffForHumans()] : null,
            ]))],
            'web_url' => $webUrl,
        ]];

        if ($fact?->whois_error) {
            $sections[] = Sections::notice(__('WHOIS'), $fact->whois_error, null, 'warn');
        }

        if ($domain->members->isNotEmpty()) {
            $sections[] = [
                'type' => 'list',
                'title' => __('Paylaşılan kişiler'),
                'data' => ['items' => $domain->members->map(fn ($member) => [
                    'title' => $member->user?->email ?? $member->email,
                    'subtitle' => $member->permission === 'write' ? __('Düzenleyebilir') : __('Salt okunur'),
                ])->values()->all()],
            ];
        }

        return $sections;
    }

    private static function cruxValue(string $label, mixed $p75): string
    {
        if (!is_numeric($p75)) {
            return '—';
        }

        return $label === 'CLS' ? Sections::decimal((float) $p75, 2) : Sections::decimal((float) $p75 / 1000, 1).' s';
    }
}
