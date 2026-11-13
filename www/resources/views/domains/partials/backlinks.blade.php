{{-- Domain sayfasi "Backlink" sekmesi (bkz. App\Support\BacklinkOverview, FetchDomainBacklinks). --}}
@php
    $snapshot = $backlinks['snapshot'];
    $previous = $backlinks['previous'];
    $failedLatest = $backlinks['failedLatest'];
    $history = $backlinks['history'];
    $isRefreshing = $backlinks['isRefreshing'];
    $isConfigured = $dataForSeoConfigured;
    $fmt = fn ($v) => $v === null ? '—' : number_format((int) $v, 0, ',', '.');
    $delta = function (?int $now, ?int $before) {
        if ($now === null || $before === null || $now === $before) {
            return null;
        }
        $diff = $now - $before;

        return ['text' => ($diff > 0 ? '+' : '') . number_format($diff, 0, ',', '.'), 'class' => $diff > 0 ? 'text-success' : 'text-danger'];
    };
    $spamClass = fn (?int $score) => $score === null ? 'bg-secondary-lt' : ($score >= 60 ? 'bg-danger-lt' : ($score >= 30 ? 'bg-warning-lt' : 'bg-success-lt'));
    $summary = $snapshot?->summary ?? [];
    $listErrors = $summary['list_errors'] ?? [];
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h3 class="mb-0">{{ __('Backlink profili') }}</h3>
        @if ($snapshot)
            <div class="text-secondary small">
                {{ __('Kaynak: DataForSEO Backlinks. Son güncelleme: :date', ['date' => $snapshot->fetched_at->format('d.m.Y H:i')]) }}
                @if ($previous)
                    · {{ __('Değişimler :date tarihli çekime göre.', ['date' => $previous->fetched_at->format('d.m.Y')]) }}
                @endif
            </div>
        @endif
    </div>
    @if ($canEdit && $isConfigured)
        <form method="post" action="{{ route('domains.backlinks.refresh', $domain) }}">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled($isRefreshing)>
                <i class="ti ti-refresh icon"></i> {{ $isRefreshing ? __('Yenileniyor…') : __('Şimdi yenile') }}
            </button>
        </form>
    @endif
</div>
@if (!$isConfigured)
    <div class="alert alert-warning">{{ __('DataForSEO yapılandırılmamış; backlink verisi çekilemiyor.') }}</div>
@endif

@if ($isRefreshing)
    <div class="alert alert-info">{{ __('Backlink verisi arka planda yenileniyor; bir iki dakika sonra sayfayı yenileyin.') }}</div>
@endif

@if ($failedLatest)
    <div class="alert alert-danger">
        {{ __('Son backlink çekimi başarısız oldu (:date): :error', ['date' => $failedLatest->fetched_at->format('d.m.Y H:i'), 'error' => $failedLatest->error]) }}
    </div>
@endif

@if ($snapshot === null)
    <div class="card">
        <div class="empty">
            <div class="empty-icon"><i class="ti ti-link fs-1"></i></div>
            <p class="empty-title">{{ __('Henüz backlink verisi yok') }}</p>
            <p class="empty-subtitle text-secondary">
                {{ __('Veri ayda bir otomatik çekilir. Beklemek istemiyorsanız "Şimdi yenile" ile hemen çekebilirsiniz.') }}
            </p>
        </div>
    </div>
@else

    <div class="row row-cards mb-3">
        @foreach ([
            ['label' => __('Backlink'), 'value' => $snapshot->backlinks, 'before' => $previous?->backlinks],
            ['label' => __('Referring domain'), 'value' => $snapshot->referring_domains, 'before' => $previous?->referring_domains],
            ['label' => __('Domain rank'), 'value' => $snapshot->rank, 'before' => $previous?->rank, 'hint' => __('DataForSEO ölçeğinde 0–1000')],
        ] as $metric)
            @php $d = $delta($metric['value'], $metric['before']); @endphp
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">{{ $metric['label'] }}</div>
                        <div class="h1 mb-0">
                            {{ $fmt($metric['value']) }}
                            @if ($d)<span class="fs-5 {{ $d['class'] }}">{{ $d['text'] }}</span>@endif
                        </div>
                        @isset($metric['hint'])<div class="text-secondary small">{{ $metric['hint'] }}</div>@endisset
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">{{ __('Spam skoru') }}</div>
                    <div class="h1 mb-0">
                        <span class="badge {{ $spamClass($snapshot->spam_score) }} fs-3">{{ $snapshot->spam_score ?? '—' }}</span>
                    </div>
                    <div class="text-secondary small">{{ __('Backlink\'lerin ortalaması, 0–100; düşük iyidir') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">{{ __('Trend') }}</h3></div>
                <div class="card-body">
                    @if ($history->count() < 2)
                        <p class="text-secondary small mb-0">{{ __('Trend için en az iki çekim gerekiyor; aylık çekimlerle dolacak.') }}</p>
                    @else
                        <x-line-chart
                            :labels="$history->map(fn ($h) => $h->fetched_at->format('d.m'))->all()"
                            :series="[
                                ['label' => __('Referring domain'), 'values' => $history->pluck('referring_domains')->all(), 'color' => '#4285F4'],
                                ['label' => __('Backlink'), 'values' => $history->pluck('backlinks')->all(), 'color' => '#34A853'],
                            ]"
                            :decimals="0"
                            :height="200"
                        />
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">{{ __('Dağılım') }}</h3></div>
                <div class="card-body">
                    @php
                        $rd = (int) ($summary['referring_domains'] ?? 0);
                        $rdNofollow = (int) ($summary['referring_domains_nofollow'] ?? 0);
                        $dofollowPct = $rd > 0 ? round(($rd - $rdNofollow) / $rd * 100) : null;
                    @endphp
                    <dl class="row mb-0">
                        <dt class="col-7 fw-normal text-secondary">{{ __('Dofollow domain oranı') }}</dt>
                        <dd class="col-5 text-end">{{ $dofollowPct === null ? '—' : '%' . $dofollowPct }}</dd>
                        <dt class="col-7 fw-normal text-secondary">{{ __('Ana domain') }}</dt>
                        <dd class="col-5 text-end">{{ $fmt($summary['referring_main_domains'] ?? null) }}</dd>
                        <dt class="col-7 fw-normal text-secondary">{{ __('Link veren sayfa') }}</dt>
                        <dd class="col-5 text-end">{{ $fmt($summary['referring_pages'] ?? null) }}</dd>
                        <dt class="col-7 fw-normal text-secondary">{{ __('Farklı IP') }}</dt>
                        <dd class="col-5 text-end">{{ $fmt($summary['referring_ips'] ?? null) }}</dd>
                        <dt class="col-7 fw-normal text-secondary">{{ __('Kırık backlink') }}</dt>
                        <dd class="col-5 text-end">{{ $fmt($summary['broken_backlinks'] ?? null) }}</dd>
                        <dt class="col-7 fw-normal text-secondary">{{ __('İlk görülme') }}</dt>
                        <dd class="col-5 text-end">{{ $summary['first_seen'] ?? '—' }}</dd>
                    </dl>
                    @if (!empty($summary['top_tlds']))
                        <div class="mt-3 d-flex flex-wrap gap-1">
                            @foreach ($summary['top_tlds'] as $tld => $count)
                                <span class="badge bg-secondary-lt">.{{ $tld }} {{ $fmt($count) }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endif

@php
    $crawlGraph = $backlinks['crawlGraph'];
    $merged = $backlinks['merged'];
    $sourceBadges = [
        \App\Support\BacklinkMerger::SOURCE_DATAFORSEO => ['DFS', 'bg-blue-lt', 'DataForSEO'],
        \App\Support\BacklinkMerger::SOURCE_CRAWLGRAPH => ['CC', 'bg-purple-lt', 'Common Crawl (CrawlGraph)'],
        \App\Support\BacklinkMerger::SOURCE_BING => ['BING', 'bg-cyan-lt', 'Bing Webmaster Tools'],
    ];
    $dfsListMissing = fn (string $field) => $snapshot !== null && $snapshot->{$field} === null;
@endphp

@if ($snapshot !== null || $crawlGraph !== null || $merged['domains'] !== [])
    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><a href="#bl-domains" class="nav-link active" data-bs-toggle="tab">{{ __('Referring domain\'ler') }} <span class="badge bg-secondary-lt ms-1">{{ count($merged['domains']) }}</span></a></li>
                @if ($snapshot !== null)
                    <li class="nav-item"><a href="#bl-anchors" class="nav-link" data-bs-toggle="tab">{{ __('Anchor metinleri') }} <span class="badge bg-secondary-lt ms-1">{{ count($snapshot->anchors ?? []) }}</span></a></li>
                @endif
                <li class="nav-item"><a href="#bl-new" class="nav-link" data-bs-toggle="tab">{{ __('Yeni') }} <span class="badge bg-success-lt ms-1">{{ count($merged['new']) }}</span></a></li>
                <li class="nav-item"><a href="#bl-lost" class="nav-link" data-bs-toggle="tab">{{ __('Kaybedilen') }} <span class="badge bg-danger-lt ms-1">{{ count($merged['lost']) }}</span></a></li>
            </ul>
        </div>
        <div class="tab-content">
            @foreach ([
                ['id' => 'bl-domains', 'rows' => $merged['domains'], 'field' => 'top_referring_domains', 'date' => 'first_seen', 'dateLabel' => __('İlk görülme'), 'intro' => __('DataForSEO (rank\'e göre ilk :n), Common Crawl ve Bing Webmaster (en çok link alan :pages sayfaya link verenler) listeleri birleştirildi; aynı domain tek satırda.', ['n' => \App\Services\DataForSeo\BacklinksFetcher::REFERRING_DOMAINS_LIMIT, 'pages' => \App\Services\Bing\BingBacklinksChecker::LINKING_PAGES_LIMIT])],
                ['id' => 'bl-new', 'rows' => $merged['new'], 'field' => 'new_referring_domains', 'date' => 'first_seen', 'dateLabel' => __('İlk görülme'), 'intro' => __('DataForSEO: son :days günde ilk kez link verenler. Common Crawl: önceki arşivde olmayıp bu arşivde görülenler.', ['days' => \App\Services\DataForSeo\BacklinksFetcher::NEW_LOST_DAYS])],
                ['id' => 'bl-lost', 'rows' => $merged['lost'], 'field' => 'lost_referring_domains', 'date' => 'lost_date', 'dateLabel' => __('Kaybedilme'), 'intro' => __('DataForSEO: son :days günde tüm linkleri kaybolanlar. Common Crawl: önceki arşivde olup bu arşivde görülmeyenler (link kaldırılmamış, sayfa taranmamış olabilir).', ['days' => \App\Services\DataForSeo\BacklinksFetcher::NEW_LOST_DAYS])],
            ] as $table)
                <div class="tab-pane {{ $table['id'] === 'bl-domains' ? 'active show' : '' }}" id="{{ $table['id'] }}">
                    <div class="card-body pb-0">
                        <p class="text-secondary small mb-0">{{ $table['intro'] }}</p>
                        @if ($dfsListMissing($table['field']))
                            <p class="text-danger small mb-0 mt-1">{{ __('DataForSEO listesi alınamadı.') }} {{ implode(' ', $listErrors) }}</p>
                        @endif
                    </div>
                    @if ($table['rows'] === [])
                        <div class="card-body text-secondary">{{ __('Kayıt yok.') }}</div>
                    @else
                        <div class="table-responsive" style="max-height: 560px;">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Domain') }}</th>
                                        <th>{{ __('Kaynak') }}</th>
                                        <th class="text-end" title="{{ __('DataForSEO ölçeğinde 0–1000') }}">{{ __('Rank') }}</th>
                                        <th class="text-end">{{ __('Backlink') }}</th>
                                        <th class="text-end" title="{{ __('Common Crawl, 0–100') }}">{{ __('Authority') }}</th>
                                        <th class="text-end">{{ __('Spam') }}</th>
                                        <th>{{ $table['dateLabel'] }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table['rows'] as $row)
                                        <tr>
                                            <td class="text-truncate" style="max-width: 320px;">
                                                <a href="https://{{ $row['domain'] }}" target="_blank" rel="noopener nofollow">{{ $row['domain'] }}</a>
                                                @if ($row['nofollow'])<span class="badge bg-secondary-lt ms-1">nofollow</span>@endif
                                                @if (($row['sample_url'] ?? null) !== null)
                                                    <div class="small text-secondary text-truncate" title="{{ $row['sample_url'] }}">
                                                        <a href="{{ $row['sample_url'] }}" class="text-secondary" target="_blank" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $row['sample_url']), 60) }}</a>
                                                        @if (($row['anchor'] ?? null) !== null) · “{{ \Illuminate\Support\Str::limit($row['anchor'], 40) }}”@endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                @foreach ($row['sources'] as $source)
                                                    <span class="badge {{ $sourceBadges[$source][1] }}" title="{{ $sourceBadges[$source][2] }}">{{ $sourceBadges[$source][0] }}</span>
                                                @endforeach
                                            </td>
                                            <td class="text-end">{{ $fmt($row['rank']) }}</td>
                                            <td class="text-end">
                                                @if ($row['backlinks'] !== null)
                                                    {{ $fmt($row['backlinks']) }}
                                                @elseif (($row['bing_links'] ?? null) !== null)
                                                    <span class="text-secondary" title="{{ __('Bing: incelenen sayfalara gelen link sayısı') }}">{{ $fmt($row['bing_links']) }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $row['authority'] ?? '—' }}</td>
                                            <td class="text-end">@if ($row['spam_score'] !== null)<span class="badge {{ $spamClass($row['spam_score']) }}">{{ $row['spam_score'] }}</span>@else — @endif</td>
                                            <td class="text-secondary">{{ $row[$table['date']] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($snapshot !== null)
                <div class="tab-pane" id="bl-anchors">
                    @php
                        $anchors = $snapshot->anchors;
                        $anchorTotal = collect($anchors ?? [])->sum('backlinks');
                    @endphp
                    <div class="card-body pb-0"><p class="text-secondary small mb-0">{{ __('En çok kullanılan anchor metinleri. Tek bir ticari anahtar kelimenin çok baskın olması doğal görünmeyebilir.') }}</p></div>
                    @if ($anchors === null)
                        <div class="card-body text-danger small">{{ __('Bu liste alınamadı.') }} {{ implode(' ', $listErrors) }}</div>
                    @elseif ($anchors === [])
                        <div class="card-body text-secondary">{{ __('Kayıt yok.') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Anchor') }}</th>
                                        <th class="text-end">{{ __('Backlink') }}</th>
                                        <th class="text-end">{{ __('Referring domain') }}</th>
                                        <th style="width: 30%">{{ __('Pay') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($anchors as $row)
                                        @php $pct = $anchorTotal > 0 ? round(($row['backlinks'] ?? 0) / $anchorTotal * 100) : 0; @endphp
                                        <tr>
                                            <td class="text-truncate" style="max-width: 360px;">
                                                @if (($row['anchor'] ?? '') === '')
                                                    <span class="text-secondary fst-italic">{{ __('(boş / görsel link)') }}</span>
                                                @else
                                                    {{ $row['anchor'] }}
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $fmt($row['backlinks']) }}</td>
                                            <td class="text-end">{{ $fmt($row['referring_domains']) }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress progress-sm flex-fill"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                                                    <span class="small text-secondary">%{{ $pct }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif

@include('domains.partials.backlinks-crawlgraph', ['crawlGraph' => $crawlGraph, 'crawlGraphConfigured' => $backlinks['crawlGraphConfigured']])
