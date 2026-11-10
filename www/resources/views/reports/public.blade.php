@extends('layout')

@section('public-shell', '1')
@section('title', $share->title ?: __(':domain SEO raporu', ['domain' => $domain->domain]))

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="{{ $share->title ?: __(':domain SEO raporu', ['domain' => $domain->domain]) }}">
    <meta property="og:description" content="{{ __('Site sağlığı, anahtar kelime sıralamaları ve yapay zekâ görünürlüğü.') }}">
    <meta property="og:type" content="website">
@endpush

@section('page-pretitle', __('SEO raporu'))
@section('page-title')
    {{ $share->title ?: $domain->domain }}
@endsection
@section('page-subtitle')
    @php $reportLastCheck = $domain->latestDomainCheck?->created_at ?? $domain->fact?->checked_at; @endphp
    {{ $domain->domain }}@if ($reportLastCheck) · {{ __('Son kontrol: :when', ['when' => $reportLastCheck->translatedFormat('d F Y H:i')]) }}@endif
@endsection
@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="ti ti-printer icon"></i> {{ __('Yazdır / PDF') }}</button>
@endsection

@section('content')
    @php
        $health = \App\Support\DomainHealth::for($domain);
        $gsc = $domain->latestDomainCheck?->gsc ?? [];
        $severityMeta = [
            'high' => ['label' => __('YÜKSEK'), 'class' => 'bg-danger-lt'],
            'medium' => ['label' => __('ORTA'), 'class' => 'bg-warning-lt'],
            'low' => ['label' => __('DÜŞÜK'), 'class' => 'bg-secondary-lt'],
        ];
        $checkIcon = [
            'ok' => 'ti-circle-check text-success',
            'warn' => 'ti-alert-circle text-warning',
            'fail' => 'ti-circle-x text-danger',
        ];
        $hasScoreHistory = !empty($scoreHistory['labels']);
        $hasDomainHistory = !empty($domainCheckHistory['labels']);
    @endphp

    <div class="d-flex flex-column gap-3 public-report">
        @if ($share->shows('summary'))
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-4">
                    <x-score-ring :score="$health->score" :tone="$health->tone()" :size="104" :stroke="9" :show-max="true" />
                    <div class="flex-fill" style="min-width: 240px">
                        <div class="h2 mb-1">{{ $health->summary() }}</div>
                        <div class="text-secondary">{{ __('Sağlık skoru; yapay zekâ botlarının erişimi, site haritası, HTTPS, sayfa hızı ve llms.txt kontrollerinden hesaplanır.') }}</div>
                    </div>
                </div>
            </div>

            @if ($health->issues !== [])
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('Yapılacaklar') }}</h3></div>
                    @foreach ($health->issues as $issue)
                        <details class="issue-item" open>
                            <summary>
                                <span class="badge severity-badge {{ $severityMeta[$issue['severity']]['class'] }}">{{ $severityMeta[$issue['severity']]['label'] }}</span>
                                <div class="flex-fill">
                                    <div class="fw-semibold">{{ $issue['title'] }}</div>
                                    <div class="small text-secondary mt-1">{{ $issue['description'] }}</div>
                                </div>
                            </summary>
                            @if ($issue['fix'])
                                <div class="issue-fix">
                                    <div class="rounded-3 border p-3 small">
                                        <div class="fw-semibold mb-1"><i class="ti ti-tool me-1"></i>{{ __('Nasıl düzeltilir?') }}</div>
                                        <div class="text-secondary">{{ $issue['fix'] }}</div>
                                    </div>
                                </div>
                            @endif
                        </details>
                    @endforeach
                </div>
            @endif

            @if ($health->checks !== [])
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ __('Tüm kontroller') }}</h3></div>
                    <div class="card-body">
                        <div class="check-grid">
                            @foreach ($health->checks as $c)
                                <div class="check-tile">
                                    <i class="ti {{ $checkIcon[$c['status']] }}" aria-hidden="true"></i>
                                    <div>
                                        <div class="fw-semibold small">{{ $c['label'] }}</div>
                                        <div class="small text-secondary">{{ $c['detail'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if ($share->shows('keywords'))
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('Anahtar kelime sıralamaları') }}</h3></div>
                @if ($domain->keywords->isEmpty())
                    <div class="card-body text-secondary">{{ __('Henüz anahtar kelime takip edilmiyor.') }}</div>
                @else
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <thead>
                                <tr>
                                    <th>{{ __('Kelime') }}</th>
                                    <th>{{ __('Google sırası') }}</th>
                                    <th>AI Overview</th>
                                    <th>{{ __('Son kontrol') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($domain->keywords as $kw)
                                    @php $last = $kw->latestCheck; @endphp
                                    <tr>
                                        <td class="fw-medium">{{ $kw->keyword }}</td>
                                        <td>
                                            @if (!$last)
                                                <span class="text-secondary">—</span>
                                            @elseif ($last->target_position)
                                                <strong class="{{ $last->target_position <= 10 ? 'text-success' : '' }}">{{ $last->target_position }}.</strong>
                                            @else
                                                <span class="text-secondary">{{ __('ilk 100\'de değil') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!$last || !$last->ai_overview_present)
                                                <span class="text-secondary">{{ __('yok') }}</span>
                                            @elseif ($last->ai_overview_target_cited)
                                                <span class="badge bg-success-lt">{{ __('kaynak gösteriliyor') }}</span>
                                            @else
                                                <span class="badge bg-warning-lt">{{ __('kaynakta değil') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-secondary small">{{ $last?->created_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        @if ($share->shows('traffic'))
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('Google trafiği') }}</h3></div>
                <div class="card-body">
                    @if (($gsc['verified'] ?? false) && !($gsc['error'] ?? null))
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-3"><div class="small text-secondary">{{ __('Tıklama') }}</div><div class="h2 mb-0" data-count-to="{{ (int) ($gsc['clicks'] ?? 0) }}">0</div></div>
                            <div class="col-6 col-md-3"><div class="small text-secondary">{{ __('Gösterim') }}</div><div class="h2 mb-0" data-count-to="{{ (int) ($gsc['impressions'] ?? 0) }}">0</div></div>
                            <div class="col-6 col-md-3"><div class="small text-secondary">CTR</div><div class="h2 mb-0">{{ isset($gsc['ctr']) ? number_format((float) $gsc['ctr'] * 100, 1) . '%' : '—' }}</div></div>
                            <div class="col-6 col-md-3"><div class="small text-secondary">{{ __('Ortalama sıra') }}</div><div class="h2 mb-0">{{ isset($gsc['position']) ? number_format((float) $gsc['position'], 1) : '—' }}</div></div>
                        </div>
                    @endif
                    @if ($hasDomainHistory)
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <div class="text-secondary small mb-2">{{ __('Search Console tıklama ve gösterim') }}</div>
                                <x-line-chart :labels="$domainCheckHistory['labels']" :series="[
                                    ['label' => __('Tıklama'), 'color' => '#4285F4', 'values' => $domainCheckHistory['gsc_clicks']],
                                    ['label' => __('Gösterim'), 'color' => '#34A853', 'values' => $domainCheckHistory['gsc_impressions']],
                                ]" :min="0" />
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="text-secondary small mb-2">{{ __('GA4 oturumları') }}</div>
                                <x-line-chart :labels="$domainCheckHistory['labels']" :series="[
                                    ['label' => __('Toplam oturum'), 'color' => '#4285F4', 'values' => $domainCheckHistory['ga4_total_sessions']],
                                    ['label' => __('Organik oturum'), 'color' => '#34A853', 'values' => $domainCheckHistory['ga4_organic_sessions']],
                                ]" :min="0" />
                            </div>
                        </div>
                    @elseif (!($gsc['verified'] ?? false))
                        <p class="text-secondary mb-0">{{ __('Trafik verisi henüz yok.') }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if ($share->shows('performance'))
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('Sayfa hızı ve Lighthouse') }}</h3></div>
                <div class="card-body">
                    @if (!$hasScoreHistory && !$hasDomainHistory)
                        <p class="text-secondary mb-0">{{ __('Performans verisi henüz yok.') }}</p>
                    @else
                        <div class="row g-3">
                            @if ($hasScoreHistory)
                                <div class="col-12 col-lg-6">
                                    <div class="text-secondary small mb-2">{{ __('Lighthouse skor trendi') }}</div>
                                    <x-line-chart :labels="$scoreHistory['labels']" :series="[
                                        ['label' => __('Performans'), 'color' => '#4285F4', 'values' => $scoreHistory['lighthouse_performance']],
                                        ['label' => 'SEO', 'color' => '#34A853', 'values' => $scoreHistory['lighthouse_seo']],
                                        ['label' => __('Erişilebilirlik'), 'color' => '#FBBC04', 'values' => $scoreHistory['lighthouse_accessibility']],
                                    ]" :min="0" :max="100" />
                                </div>
                            @endif
                            @if ($hasDomainHistory)
                                <div class="col-12 col-lg-6">
                                    <div class="text-secondary small mb-2">{{ __('Gerçek kullanıcı yükleme süresi (ms)') }}</div>
                                    <x-line-chart :labels="$domainCheckHistory['labels']" :series="[
                                        ['label' => 'LCP', 'color' => '#4285F4', 'values' => $domainCheckHistory['crux_lcp']],
                                        ['label' => 'INP', 'color' => '#EA4335', 'values' => $domainCheckHistory['crux_inp']],
                                    ]" :min="0" value-suffix="ms" />
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($share->shows('ai'))
            @php $aiSnapshot = $domain->aiVisibilitySnapshot(); @endphp
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('Google AI Overview görünürlüğü') }}</h3></div>
                <div class="card-body">
                    @if ($aiSnapshot['score'] === null)
                        <p class="text-secondary mb-0">{{ __('Takip edilen kelimelerde henüz AI Overview gözlemlenmedi.') }}</p>
                    @else
                        <div class="h1 mb-1">{{ number_format($aiSnapshot['score'], 0) }}%</div>
                        <div class="text-secondary small mb-3">{{ __(':cited / :present anahtar kelimede AI Overview kaynağında domain gösteriliyor', ['cited' => $aiSnapshot['cited_count'], 'present' => $aiSnapshot['present_count']]) }}</div>
                        @if ($hasScoreHistory)
                            <x-line-chart :labels="$scoreHistory['labels']" :series="[['label' => __('Görünürlük'), 'color' => '#34A853', 'values' => $scoreHistory['ai_visibility']]]" :min="0" :max="100" value-suffix="%" />
                        @endif
                    @endif
                </div>
            </div>
        @endif

        <p class="text-center text-secondary small mb-0">
            {{ __('Bu rapor :app ile oluşturuldu. Veriler sayfa her açıldığında güncellenir.', ['app' => config('app.name')]) }}
        </p>
    </div>
@endsection
