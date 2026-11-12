@extends('layout')

@section('title', $domain->domain)

@section('page-pretitle')
    <a href="{{ route('dashboard') }}" class="text-secondary">&larr; {{ __('Domainler') }}</a>
@endsection
@section('page-title')
    {{ $domain->domain }}
    @if (auth()->user()->is_admin)
        <span class="badge bg-secondary-lt ms-2">{{ __('Sahibi: :email', ['email' => $domain->user?->email ?? '—']) }}</span>
    @endif
@endsection
@if ($accessLevel !== \App\Models\Domain::ACCESS_OWNER)
    @section('page-subtitle')
        <i class="ti ti-users me-1"></i>{{ __(':email paylaştı', ['email' => $domain->user?->email ?? '—']) }}
        · {{ $canEdit ? __('Düzenleyebilir') : __('Salt okunur') }}
    @endsection
@endif
@section('page-actions')
    @if ($canManage)
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-domain-members">
            <i class="ti ti-users icon"></i> {{ __('Erişim') }}
            @if ($domain->members->isNotEmpty())
                <span class="badge bg-secondary-lt ms-1">{{ $domain->members->count() }}</span>
            @endif
        </button>
    @elseif ($membership)
        <form method="post" action="{{ route('domains.members.destroy', [$domain, $membership]) }}" class="d-inline" onsubmit="return confirm({{ json_encode(__(':domain paylaşımından ayrılmak istiyor musunuz?', ['domain' => $domain->domain])) }});">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost-danger"><i class="ti ti-logout icon"></i> {{ __('Paylaşımdan ayrıl') }}</button>
        </form>
    @endif
    @if ($canEdit)
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-share-report">
            <i class="ti ti-share icon"></i> {{ __('Paylaş') }}
        </button>
        <form method="post" action="{{ route('domains.check', $domain) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <i class="ti ti-refresh icon"></i> {{ __('Site Kontrolü Yap') }}
            </button>
        </form>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-keyword">
            <i class="ti ti-plus icon"></i> {{ __('Anahtar Kelime Ekle') }}
        </button>
    @endif
@endsection

@section('content')
    @php
        $health = \App\Support\DomainHealth::for($domain);
        $overviewGsc = $domain->latestDomainCheck?->gsc ?? [];
        $overviewLastCheck = $domain->latestDomainCheck?->created_at ?? $domain->fact?->checked_at;
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
        $hasSitemapPages = $sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > 0;
    @endphp

    {{-- Ozet basligi --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-4">
            <x-score-ring :score="$health->score" :tone="$health->tone()" :size="104" :stroke="9" :show-max="true" />
            <div class="flex-fill" style="min-width: 260px">
                <div class="h2 mb-1">{{ $health->summary() }}</div>
                <div class="text-secondary">
                    @if (!$health->hasData)
                        {{ __('İlk site kontrolü henüz tamamlanmadı. "Site Kontrolü Yap" ile hemen başlatabilirsiniz.') }}
                    @elseif ($health->issues === [])
                        {{ __('Temel teknik kontrollerin hepsi geçti. Anahtar kelimelerinizi ve yapay zekâ görünürlüğünüzü takip etmeye devam edin.') }}
                    @else
                        {{ __('Aşağıdaki konular arama motorlarında ve yapay zekâ asistanlarında görünürlüğünüzü düşürüyor. En önemlilerinden başlayın.') }}
                    @endif
                </div>
                @if ($overviewLastCheck)
                    <div class="small text-secondary mt-2">{{ __('Son kontrol: :when', ['when' => $overviewLastCheck->diffForHumans()]) }}</div>
                @endif
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs domain-tabs mb-3" role="tablist" data-remember-tabs="domain-{{ $domain->id }}">
        <li class="nav-item" role="presentation"><a href="#tab-overview" class="nav-link active" data-bs-toggle="tab" role="tab" aria-selected="true"><i class="ti ti-layout-dashboard me-1"></i>{{ __('Genel bakış') }}</a></li>
        <li class="nav-item" role="presentation"><a href="#tab-keywords" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-key me-1"></i>{{ __('Anahtar kelimeler') }} <span class="badge bg-secondary-lt ms-1">{{ $domain->keywords->count() }}</span></a></li>
        <li class="nav-item" role="presentation"><a href="#tab-health" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-heart-rate-monitor me-1"></i>{{ __('Site sağlığı') }}@if (count($health->issues) > 0) <span class="badge bg-warning-lt ms-1">{{ count($health->issues) }}</span>@endif</a></li>
        <li class="nav-item" role="presentation"><a href="#tab-ai" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-sparkles me-1"></i>{{ __('Yapay zekâ görünürlüğü') }}</a></li>
        @if ($hasSitemapPages)
            <li class="nav-item" role="presentation"><a href="#tab-pages" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-files me-1"></i>{{ __('Sayfalar') }}</a></li>
        @endif
        <li class="nav-item" role="presentation"><a href="#tab-indexnow" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-bolt me-1"></i>{{ __('Hızlı indeksleme') }}</a></li>
        <li class="nav-item" role="presentation"><a href="#tab-competitors" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-users me-1"></i>{{ __('Rakipler') }}</a></li>
        <li class="nav-item" role="presentation"><a href="#tab-history" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-chart-line me-1"></i>{{ __('Geçmiş') }}</a></li>
        <li class="nav-item" role="presentation"><a href="#tab-info" class="nav-link" data-bs-toggle="tab" role="tab" aria-selected="false"><i class="ti ti-id me-1"></i>{{ __('Domain bilgisi') }}</a></li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane active show" id="tab-overview" role="tabpanel">
        <div class="row g-3">
            <div class="col-12 col-lg-8 d-flex flex-column gap-3">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('Önce bunları düzeltin') }}</h3>
                            <div class="card-subtitle mt-1">{{ __('Etkisi en büyük olandan en küçüğe sıralandı. Ayrıntı ve çözüm için konuya tıklayın.') }}</div>
                        </div>
                    </div>
                    @if (!$health->hasData)
                        <div class="card-body text-secondary">{{ __('Kontrol tamamlanınca öncelikli konular burada listelenecek.') }}</div>
                    @elseif ($health->issues === [])
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar bg-success-lt"><i class="ti ti-check"></i></span>
                            <div>{{ __('Düzeltilmesi gereken bir konu yok. Harika!') }}</div>
                        </div>
                    @else
                        @foreach ($health->issues as $index => $issue)
                            <details class="issue-item" @if ($index === 0) open @endif>
                                <summary>
                                    <span class="badge severity-badge {{ $severityMeta[$issue['severity']]['class'] }}">{{ $severityMeta[$issue['severity']]['label'] }}</span>
                                    <div class="flex-fill">
                                        <div class="fw-semibold">{{ $issue['title'] }}</div>
                                        <div class="small text-secondary mt-1">{{ $issue['description'] }}</div>
                                    </div>
                                    <i class="ti ti-chevron-down issue-chevron" aria-hidden="true"></i>
                                </summary>
                                @if ($issue['fix'])
                                    <div class="issue-fix">
                                        <div class="rounded-3 border p-3 small">
                                            <div class="fw-semibold mb-1"><i class="ti ti-tool me-1"></i>{{ __('Nasıl düzeltilir?') }}</div>
                                            <div class="text-secondary">{{ $issue['fix'] }}</div>
                                            <div class="d-flex flex-wrap gap-2 mt-3">
                                                @if ($canEdit)
                                                    <form method="post" action="{{ route('domains.check', $domain) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm">{{ __('Düzelttim, tekrar kontrol et') }}</button>
                                                    </form>
                                                @endif
                                                <a href="#tab-health" class="btn btn-sm btn-link" data-tab-jump="#tab-health">{{ __('Teknik ayrıntılar') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </details>
                        @endforeach
                    @endif
                </div>

                @if ($health->checks !== [])
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Tüm kontroller') }}</h3>
                            <div class="card-actions small text-secondary d-flex gap-3">
                                <span><i class="ti ti-circle-check text-success"></i> {{ collect($health->checks)->where('status', 'ok')->count() }}</span>
                                <span><i class="ti ti-alert-circle text-warning"></i> {{ collect($health->checks)->where('status', 'warn')->count() }}</span>
                                <span><i class="ti ti-circle-x text-danger"></i> {{ collect($health->checks)->where('status', 'fail')->count() }}</span>
                            </div>
                        </div>
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
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column gap-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="card-title mb-0">{{ __('Anahtar kelimeler') }}</h3>
                            <a href="#tab-keywords" class="small" data-tab-jump="#tab-keywords">{{ __('Tümünü gör') }}</a>
                        </div>
                        @if ($domain->keywords->isEmpty())
                            <p class="text-secondary small mb-3">{{ __('Hangi aramalarda görünmek istediğinizi ekleyin; Google ve yapay zekâ yanıtlarındaki yerinizi takip edelim.') }}</p>
                            @if ($canEdit)
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-add-keyword"><i class="ti ti-plus icon"></i> {{ __('Anahtar Kelime Ekle') }}</button>
                            @endif
                        @else
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                @foreach ($domain->keywords->take(5) as $kw)
                                    @php
                                        $pos = $kw->latestCheck?->target_position;
                                    @endphp
                                    <li class="d-flex justify-content-between gap-2">
                                        <a href="{{ route('keywords.show', $kw) }}" class="text-reset text-truncate">{{ $kw->keyword }}</a>
                                        <span class="small {{ $pos && $pos <= 10 ? 'text-success fw-semibold' : 'text-secondary' }}">{{ $pos ? $pos . '.' : '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                @if (($overviewGsc['verified'] ?? false) && !($overviewGsc['error'] ?? null))
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">{{ __('Google, son dönem') }}</h3>
                            <div class="row">
                                <div class="col-6">
                                    <div class="small text-secondary">{{ __('Tıklama') }}</div>
                                    <div class="h2 mb-0" data-count-to="{{ (int) ($overviewGsc['clicks'] ?? 0) }}">0</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-secondary">{{ __('Gösterim') }}</div>
                                    <div class="h2 mb-0" data-count-to="{{ (int) ($overviewGsc['impressions'] ?? 0) }}">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="card-title mb-0">{{ __('Domain bilgisi') }}</h3>
                            <a href="#tab-info" class="small" data-tab-jump="#tab-info">{{ __('Ayrıntı') }}</a>
                        </div>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-secondary fw-normal">{{ __('Kayıt Süresi Sonu') }}</dt>
                            <dd class="col-7 mb-1 fw-semibold">
                                @if ($domain->fact?->whois_expires_at)
                                    {{ $domain->fact->whois_expires_at->format('Y-m-d') }}
                                    @if ($domain->fact->whois_expires_at->isFuture() && now()->diffInDays($domain->fact->whois_expires_at) < 45)
                                        <span class="badge bg-danger-lt ms-1">{{ __('Yakında doluyor') }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                            <dt class="col-5 text-secondary fw-normal">{{ __('Registrar') }}</dt>
                            <dd class="col-7 mb-1">{{ $domain->fact?->whois_registrar ?: '—' }}</dd>
                            <dt class="col-5 text-secondary fw-normal">{{ __('Domain Yaşı') }}</dt>
                            <dd class="col-7 mb-0">{{ $domain->whoisAgeInYears() !== null ? __(':years yıl', ['years' => $domain->whoisAgeInYears()]) : '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-keywords" role="tabpanel">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('Anahtar Kelimeler') }}</h3>
            @if ($canEdit && $dataForSeoConfigured && $domain->keywords->isNotEmpty())
                <div class="card-actions">
                    <form method="post" action="{{ route('domains.keyword-metrics.refresh', $domain) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="{{ __('Aylık arama hacmi, CPC, keyword difficulty ve arama niyeti (DataForSEO). 30 günden yeni metrikler tekrar sorgulanmaz.') }}">
                            <i class="ti ti-chart-bar icon"></i> {{ __('Metrikleri getir') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
        <div class="card-body border-bottom py-2">
            <p class="text-secondary mb-0">{{ __('"Kontrol Et" butonu SERP + on-page + Lighthouse taramasını aynı anda çalıştırır; bu 20-60 saniye sürebilir, sayfa kapanmadan bekleyin.') }}</p>
        </div>

        @php
            $trackedKeywords = $domain->keywords->pluck('keyword')->map(fn ($k) => mb_strtolower(trim($k)));
            $dismissedSuggestions = collect($domain->dismissed_keyword_suggestions ?? []);
            $suggestedKeywords = collect($domain->latestDomainCheck?->suggested_keywords ?? [])
                ->reject(fn ($s) => $trackedKeywords->contains(mb_strtolower($s['phrase'])))
                ->reject(fn ($s) => $dismissedSuggestions->contains(mb_strtolower($s['phrase'])));
        @endphp
        @if ($canEdit && $suggestedKeywords->isNotEmpty())
            <div class="card-body border-bottom py-3">
                <div class="text-secondary small mb-2">
                    {{ __('Ana sayfa HTML\'inden otomatik çıkarılan anahtar kelime önerileri — eklemek için "+", istemiyorsanız "×" tıklayın:') }}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($suggestedKeywords as $suggestion)
                        <div class="btn-group">
                            <form method="post" action="{{ route('keywords.store', $domain) }}">
                                @csrf
                                <input type="hidden" name="keyword" value="{{ $suggestion['phrase'] }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-plus icon"></i> {{ $suggestion['phrase'] }}
                                </button>
                            </form>
                            <form method="post" action="{{ route('domains.keyword-suggestions.dismiss', $domain) }}">
                                @csrf
                                <input type="hidden" name="phrase" value="{{ $suggestion['phrase'] }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Öneriyi kaldır: :phrase', ['phrase' => $suggestion['phrase']]) }}">
                                    <i class="ti ti-x"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($domain->keywords->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-key fs-1"></i></div>
                <p class="empty-title">{{ __('Henüz anahtar kelime eklenmedi') }}</p>
                @if ($canEdit)
                <div class="empty-action">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-keyword">
                        <i class="ti ti-plus icon"></i> {{ __('Anahtar Kelime Ekle') }}
                    </button>
                </div>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>{{ __('Kelime') }}</th>
                            <th>{{ __('Sayfa') }}</th>
                            <th class="text-end" data-bs-toggle="tooltip" title="{{ __('Aylık ortalama Google arama hacmi (DataForSEO)') }}">{{ __('Hacim') }}</th>
                            <th class="text-end" data-bs-toggle="tooltip" title="{{ __('Keyword difficulty: 0-100 arası organik sıralama zorluğu') }}">KD</th>
                            <th>{{ __('Son sıralama') }}</th>
                            <th>AI Overview</th>
                            @php
                                $showAiColumn = $domain->llm_visibility_enabled || $domain->keywords->contains(fn ($k) => $k->latestAiCheck !== null);
                            @endphp
                            @if ($showAiColumn)
                                <th>{{ __('AI Görünürlük Kontrolü (ChatGPT/Claude/Gemini)') }}</th>
                            @endif
                            <th>{{ __('Lighthouse (Perf/SEO/Eris/BP)') }}</th>
                            <th>{{ __('Son kontrol') }}</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($domain->keywords as $kw)
                            @php
                                $last = $kw->latestCheck;
                            @endphp
                            <tr>
                                <td><a href="{{ route('keywords.show', $kw) }}" class="fw-medium">{{ $kw->keyword }}</a></td>
                                <td class="text-secondary">{{ $kw->url ?: $domain->rootUrl() }}</td>
                                <td class="text-end">
                                    @if ($kw->search_volume !== null)
                                        <span @if ($kw->search_intent) data-bs-toggle="tooltip" title="{{ __('Arama niyeti: :intent', ['intent' => __('intent.' . $kw->search_intent)]) }}{{ $kw->cpc !== null ? ' · CPC: $' . number_format($kw->cpc, 2) : '' }}" @endif>{{ number_format($kw->search_volume, 0, ',', '.') }}</span>
                                    @elseif ($kw->metrics_error)
                                        <span class="text-danger" data-bs-toggle="tooltip" title="{{ $kw->metrics_error }}">!</span>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($kw->keyword_difficulty !== null)
                                        <span class="badge {{ $kw->keyword_difficulty >= 70 ? 'bg-danger-lt' : ($kw->keyword_difficulty >= 40 ? 'bg-warning-lt' : 'bg-success-lt') }}">{{ $kw->keyword_difficulty }}</span>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if (!$last)
                                        <span class="text-secondary">-</span>
                                    @elseif ($last->blocked)
                                        <span class="badge bg-warning-lt">{{ __('engellendi') }}</span>
                                    @else
                                        <span data-bs-toggle="tooltip" title="{{ __('Masaüstü') }}"><i class="ti ti-device-desktop text-secondary"></i>
                                            @if ($last->target_position)
                                                <strong>{{ $last->target_position }}</strong>
                                            @else
                                                <span class="text-secondary">{{ __('bulunamadı') }}</span>
                                            @endif
                                        </span>
                                        @if ($last->mobile_blocked !== null)
                                            <span class="ms-2" data-bs-toggle="tooltip" title="{{ __('Mobil') }}"><i class="ti ti-device-mobile text-secondary"></i>
                                                @if ($last->mobile_blocked)
                                                    <span class="text-warning">!</span>
                                                @elseif ($last->mobile_target_position)
                                                    <strong>{{ $last->mobile_target_position }}</strong>
                                                @else
                                                    <span class="text-secondary">-</span>
                                                @endif
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @if (!$last)
                                        <span class="text-secondary">-</span>
                                    @elseif ($last->ai_overview_present)
                                        @if ($last->ai_overview_target_cited)
                                            <span class="badge bg-success-lt">{{ __('var, kaynakta') }}</span>
                                        @else
                                            <span class="badge bg-warning-lt">{{ __('var, kaynakta değil') }}</span>
                                        @endif
                                    @else
                                        <span class="text-secondary">{{ __('yok') }}</span>
                                    @endif
                                </td>
                                @if ($showAiColumn)
                                    @php
                                        // Her asistanin son sonucu; manuel MCP kontrolleri
                                        // ayri kayit oldugu icin birlestirilir.
                                        $aiResults = $kw->latestAiResultsByProvider();
                                    @endphp
                                    <td>
                                        @if ($aiResults === [])
                                            <span class="text-secondary">-</span>
                                        @else
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach ($aiResults as $provider => ['result' => $result, 'exploration' => $aiCheck])
                                                    @php
                                                        $model = $result['model'] ?? null;
                                                    @endphp
                                                    <a href="{{ route('domains.prompts.show', [$domain, $aiCheck]) }}" class="d-inline-flex flex-wrap gap-1 text-reset text-decoration-none" title="{{ $aiCheck->created_at->toDateTimeString() }}">
                                                    @if ($result['error'] ?? null)
                                                        <span class="badge bg-danger-lt" data-bs-toggle="tooltip" title="{{ ($model ? '[' . $model . '] ' : '') . \App\Support\StoredText::t($result['error']) }}">
                                                            {{ \App\Support\AiAssistant::label($provider) }}@if ($model) <span class="fw-normal text-muted" style="font-size: 0.75em;">({{ $model }})</span>@endif
                                                        </span>
                                                    @elseif ($result['present'] ?? false)
                                                        <span class="badge bg-success-lt" data-bs-toggle="tooltip" title="{{ $model ? '[' . $model . '] ' . __('Domain geçiyor') : '' }}">
                                                            {{ \App\Support\AiAssistant::label($provider) }}@if ($model) <span class="fw-normal text-muted" style="font-size: 0.75em;">({{ $model }})</span>@endif
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary-lt" data-bs-toggle="tooltip" title="{{ $model ? '[' . $model . '] ' . __('Domain geçmiyor') : '' }}">
                                                            {{ \App\Support\AiAssistant::label($provider) }}@if ($model) <span class="fw-normal text-muted" style="font-size: 0.75em;">({{ $model }})</span>@endif
                                                        </span>
                                                    @endif
                                                    @if ($result['follow_up_response'] ?? null)
                                                        @if ($result['follow_up_present'] ?? false)
                                                            <span class="badge bg-success-lt" data-bs-toggle="tooltip" title="{{ __("Türkiye'den:") }}{{ $model ? ' [' . $model . ']' : '' }}">{{ \App\Support\AiAssistant::label($provider) }} TR</span>
                                                        @else
                                                            <span class="badge bg-secondary-lt" data-bs-toggle="tooltip" title="{{ __("Türkiye'den:") }}{{ $model ? ' [' . $model . ']' : '' }}">{{ \App\Support\AiAssistant::label($provider) }} TR</span>
                                                        @endif
                                                    @endif
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-secondary">
                                    @if (!$last)
                                        -
                                    @else
                                        {{ $last->lighthouse_performance ?? '-' }} /
                                        {{ $last->lighthouse_seo ?? '-' }} /
                                        {{ $last->lighthouse_accessibility ?? '-' }} /
                                        {{ $last->lighthouse_best_practices ?? '-' }}
                                    @endif
                                </td>
                                <td class="text-secondary">{{ $last?->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    @if ($canEdit)
                                    <div class="btn-list flex-nowrap">
                                        <form method="post" action="{{ route('keywords.check', $kw) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-icon btn-outline-primary" aria-label="{{ __('Kontrol Et') }}">
                                                <i class="ti ti-refresh"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('keywords.destroy', $kw) }}" onsubmit="return confirm('{{ __('Bu anahtar kelime silinsin mi?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-ghost-danger" aria-label="{{ __('Sil') }}">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    </div>

    <div class="tab-pane" id="tab-health" role="tabpanel">
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Site Kontrolü') }}</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                {{ __("Belirli bir anahtar kelimeye değil, domain'in tamamına ait kontroller: AI crawler'ların robots.txt erişimi, sitemap.xml, llms.txt ve güvenlik header'ları.") }}
            </p>

            @if ($domain->fact?->checked_at || $domain->latestDomainCheck)
                @php
                    // $fact, ayni domain string'ini takip eden HERHANGI bir
                    // kullanicinin kontrolunden gelen ortak/paylasilan
                    // bilgidir (bkz. Domain::fact()) - bu Domain kaydinin
                    // kendi kontrolu hic calismamis olsa bile guncel
                    // gorunur. $domainCheck ise SADECE bu Domain kaydina ozel
                    // (sitemap/GSC/GA4/Bing) - hic calismadiysa null olabilir.
                    $fact = $domain->fact;
                    $domainCheck = $domain->latestDomainCheck;
                    $aiCrawlers = $fact?->ai_crawlers ?? [];
                    $sitemapData = $domainCheck?->sitemap ?? [];
                    $llmsTxt = $fact?->llms_txt ?? [];
                    $securityHeaders = $fact?->security_headers ?? [];
                    $canonicalHostData = $fact?->canonical_host ?? [];
                    $cruxData = $fact?->crux ?? [];
                    $gscData = $domainCheck?->gsc ?? [];
                    $ga4Data = $domainCheck?->ga4 ?? [];
                    $bingData = $domainCheck?->bing_backlinks ?? [];
                @endphp

                <div class="text-secondary small mb-2">
                    @if ($domainCheck)
                        {{ __('Son kontrol: :date', ['date' => $domainCheck->created_at->format('Y-m-d H:i:s')]) }}
                    @else
                        {{ __('Bu domain için henüz kendi kontrolünüz çalışmadı — aşağıdaki ortak bilgiler aynı domain\'in başka bir kaydından geliyor.') }}
                    @endif
                </div>
                <div class="mb-3">
                    @include('domains._check-badges', ['domainCheck' => $domainCheck, 'fact' => $fact])
                </div>

                @if (!empty($driftChanges))
                    <div class="alert alert-warning">
                        <div class="fw-medium mb-1">{{ __('Bir önceki kontrole göre değişenler:') }}</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($driftChanges as $change)
                                <li>{{ $change }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="accordion" id="site-check-accordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-crawlers">
                                {{ __("AI crawler erişimi (robots.txt)") }}
                            </button>
                        </h2>
                        <div id="ac-crawlers" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($aiCrawlers['found'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Crawler</th><th>{{ __('Kim') }}</th><th>{{ __('Durum') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($aiCrawlers['crawlers'] as $token => $info)
                                                <tr>
                                                    <td>{{ $token }}</td>
                                                    <td>{{ $info['label'] }}</td>
                                                    <td>
                                                        @if ($info['allowed'])
                                                            <span class="badge bg-success-lt">{{ __('Açık') }}</span>
                                                        @else
                                                            <span class="badge bg-warning-lt">{{ __('Engelli') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">{{ __('Kaynak:') }} <a href="{{ $aiCrawlers['url'] }}" target="_blank" rel="noopener">{{ $aiCrawlers['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ __(':url bulunamadı — varsayılan olarak tüm crawler\'lar için açık kabul edilir.', ['url' => $aiCrawlers['url'] ?? '']) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-sitemap">
                                {{ __('Sitemap') }}
                            </button>
                        </h2>
                        <div id="ac-sitemap" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($sitemapData['found'] ?? false)
                                    @if ($sitemapData['is_valid_xml'] ?? false)
                                        <p>
                                            {{ ($sitemapData['is_sitemap_index'] ?? false) ? __('Sitemap index') : __('Sitemap') }},
                                            {{ $sitemapData['url_count'] ?? 0 }}
                                            {{ ($sitemapData['is_sitemap_index'] ?? false) ? __('alt-sitemap') : 'URL' }} {{ __('içeriyor.') }}
                                        </p>
                                    @else
                                        <p class="text-danger">{{ \App\Support\StoredText::t($sitemapData['error'] ?? null) ?: __('Geçersiz XML.') }}</p>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $sitemapData['url'] }}" target="_blank" rel="noopener">{{ $sitemapData['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ __(':url bulunamadı.', ['url' => $sitemapData['url'] ?? '']) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-llms">
                                llms.txt
                            </button>
                        </h2>
                        <div id="ac-llms" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($llmsTxt['found'] ?? false)
                                    @if ($llmsTxt['preview'] ?? null)
                                        <pre class="bg-body-secondary p-2 rounded">{{ $llmsTxt['preview'] }}</pre>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $llmsTxt['url'] }}" target="_blank" rel="noopener">{{ $llmsTxt['url'] }}</a></p>
                                    @include('domains.partials.llms-txt-validation', ['validation' => $llmsTxt['validation'] ?? null])
                                @else
                                    <p class="text-secondary mb-0">{{ __(':url bulunamadı.', ['url' => $llmsTxt['url'] ?? '']) }}</p>
                                @endif
                                <a href="{{ route('domains.llms-txt', $domain) }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="ti ti-file-text icon"></i> {{ ($llmsTxt['found'] ?? false) ? __('Sitemap\'ten yeni taslak oluştur') : __('llms.txt taslağı oluştur') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-security">
                                {{ __("Güvenlik header'ları") }}
                            </button>
                        </h2>
                        <div id="ac-security" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($securityHeaders['reachable'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Header</th><th>{{ __('Değer') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($securityHeaders['headers'] ?? [] as $name => $value)
                                                <tr>
                                                    <td>{{ $name }}</td>
                                                    <td>
                                                        @if ($value)
                                                            {{ $value }}
                                                        @else
                                                            <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>{{ __('HTTP &rarr; HTTPS yönlendirme') }}</td>
                                                <td>
                                                    @if ($securityHeaders['http_redirects_to_https'] ?? false)
                                                        <span class="badge bg-success-lt">{{ __('var') }}</span>
                                                    @else
                                                        <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-danger mb-0">{{ __('Domaine erişilemedi.') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-canonical">
                                {{ __('Kanonik host') }}
                            </button>
                        </h2>
                        <div id="ac-canonical" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($canonicalHostData['redirected'] ?? false)
                                    <p class="mb-0">
                                        {!! __('<code>:originalHost</code>, HTTP :status ile <code>:canonicalHost</code> adresine yönleniyor. Gerçek trafik (ve CrUX verisi) bu host altında toplanır; site-geneli kontroller (ve CrUX sorgusu) bu adrese göre yapılır.', ['originalHost' => $canonicalHostData['original_host'], 'status' => $canonicalHostData['redirect_status'], 'canonicalHost' => $canonicalHostData['canonical_host']]) !!}
                                    </p>
                                @else
                                    <p class="text-secondary mb-0">
                                        {!! __('<code>:host</code> kalıcı bir yönlendirme yapmıyor — bu host kanonik kabul edildi.', ['host' => $canonicalHostData['original_host'] ?? $domain->domain]) !!}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-crux">
                                {{ __('CrUX (gerçek kullanıcı verisi)') }}
                            </button>
                        </h2>
                        <div id="ac-crux" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @php
                                    $cruxMetricDescriptions = [
                                        'LCP' => __('Largest Contentful Paint — sayfadaki en büyük içerik öğesinin (görsel, video, blok metin) görünür hale gelme süresi. Yükleme hızını gösterir.'),
                                        'INP' => __('Interaction to Next Paint — kullanıcının tıklama/dokunma/tuş basma gibi etkileşimlerine sayfanın tepki verme süresi. Etkileşim duyarlılığını gösterir.'),
                                        'CLS' => __('Cumulative Layout Shift — sayfa yüklenirken öğelerin beklenmedik şekilde yer değiştirmesinin toplam skoru. Görsel kararlılığı gösterir.'),
                                        'FCP' => __('First Contentful Paint — tarayıcının ilk metin/görsel içeriği ekrana çizdiği an. Sayfanın "boş değil" hissini verdiği süredir.'),
                                        'TTFB' => __('Time to First Byte — tarayıcının sunucudan yanıtın ilk baytını alma süresi. Sunucu/ağ gecikmesini gösterir.'),
                                    ];
                                @endphp
                                @if (!($cruxData['configured'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {!! __('CrUX API anahtarı tanımlı değil (<code>CRUX_API_KEY</code> veya <code>PSI_API_KEY</code>).') !!}
                                    </p>
                                @elseif ($cruxData['error'] ?? null)
                                    <p class="text-danger mb-1">
                                        {!! __('<code>:origin</code> sorgulanırken hata oluştu: :error', ['origin' => $cruxData['origin'] ?? '', 'error' => e(\App\Support\StoredText::t($cruxData['error']))]) !!}
                                    </p>
                                    <p class="text-secondary mb-0">
                                        {{ __('"PERMISSION_DENIED" / "API_KEY_SERVICE_BLOCKED" görüyorsanız: Google Cloud Console\'da API anahtarının API kısıtlamaları listesine "Chrome UX Report API"yi ekleyin ve API\'nin projede etkin olduğundan emin olun.') }}
                                    </p>
                                @elseif (!($cruxData['found'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {!! __('<code>:origin</code> için CrUX verisi bulunamadı (yeterli gerçek kullanıcı trafiği olmayabilir).', ['origin' => $cruxData['origin'] ?? '']) !!}
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>{{ __('Metrik') }}</th><th>p75</th><th>{{ __('Değerlendirme') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($cruxData['metrics'] ?? [] as $metric)
                                                <tr>
                                                    <td>
                                                        <span
                                                            @if ($cruxMetricDescriptions[$metric['label']] ?? null)
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                title="{{ $cruxMetricDescriptions[$metric['label']] }}"
                                                                style="cursor: help; border-bottom: 1px dotted currentColor;"
                                                            @endif
                                                        >{{ $metric['label'] }}</span>
                                                    </td>
                                                    <td>{{ $metric['p75'] }}</td>
                                                    <td>
                                                        @if ($metric['rating'] === 'good')
                                                            <span class="badge bg-success-lt">{{ __('iyi') }}</span>
                                                        @elseif ($metric['rating'] === 'needs_improvement')
                                                            <span class="badge bg-warning-lt">{{ __('geliştirilmeli') }}</span>
                                                        @else
                                                            <span class="badge bg-danger-lt">{{ __('zayıf') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        {{ __('Köken:') }} <code>{{ $cruxData['origin'] }}</code>
                                        @if ($cruxData['collection_period'] ?? null)
                                            &middot; {{ __('Veri periyodu:') }} {{ $cruxData['collection_period'] }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-gsc">
                                Search Console
                            </button>
                        </h2>
                        <div id="ac-gsc" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if (!$domain->user?->hasGoogleOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        {!! __('Google hesabınız bağlı değil. Search Console verisi için üstteki ":link" bağlantısından tekrar giriş yapın.', ['link' => __('Google hesabını bağla')]) !!}
                                    </p>
                                @elseif ($gscData['error'] ?? null)
                                    <p class="text-danger mb-0">{{ __('Search Console sorgulanırken hata oluştu: :error', ['error' => \App\Support\StoredText::t($gscData['error'])]) }}</p>
                                @elseif (!($gscData['verified'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {{ __("Bu domain, bağlı Google hesabınızın Search Console'unda doğrulanmış bir mülk (property) olarak bulunamadı.") }}
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                <tr><td>{{ __('Tıklama') }}</td><td>{{ number_format($gscData['clicks'] ?? 0) }}</td></tr>
                                                <tr><td>{{ __('Gösterim') }}</td><td>{{ number_format($gscData['impressions'] ?? 0) }}</td></tr>
                                                <tr><td>CTR</td><td>{{ number_format((($gscData['ctr'] ?? 0) * 100), 2) }}%</td></tr>
                                                <tr><td>{{ __('Ortalama pozisyon') }}</td><td>{{ $gscData['average_position'] ? number_format($gscData['average_position'], 1) : '-' }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        {{ __('Mülk:') }} <code>{{ $gscData['site_url'] }}</code>
                                        @if (($gscData['period_start'] ?? null) && ($gscData['period_end'] ?? null))
                                            &middot; {{ $gscData['period_start'] }} — {{ $gscData['period_end'] }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-ga4">
                                Google Analytics 4
                            </button>
                        </h2>
                        <div id="ac-ga4" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($ga4Data['disabled'] ?? false)
                                    <p class="text-secondary mb-2">
                                        {{ __('GA4 entegrasyonu şu anda devre dışı — Google\'ın "analytics.readonly" kapsamı için istediği kullanım açıklaması/demo video doğrulama süreci tamamlanınca etkinleştirilecek. Property ID\'yi şimdiden girebilirsiniz.') }}
                                    </p>
                                @elseif (!auth()->user()->hasGoogleOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        {!! __('Google hesabınız bağlı değil. GA4 verisi için üstteki ":link" bağlantısından tekrar giriş yapın.', ['link' => __('Google hesabını bağla')]) !!}
                                    </p>
                                @elseif ($ga4Data['error'] ?? null)
                                    <p class="text-danger mb-0">{{ __('GA4 sorgulanırken hata oluştu: :error', ['error' => \App\Support\StoredText::t($ga4Data['error'])]) }}</p>
                                @elseif (empty($ga4Data['property_id']))
                                    <p class="text-secondary mb-2">{{ __('GA4 Property ID girilmedi.') }}</p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                <tr><td>{{ __('Toplam oturum (28 gün)') }}</td><td>{{ number_format($ga4Data['total_sessions'] ?? 0) }}</td></tr>
                                                <tr><td>{{ __('Organik arama oturumu') }}</td><td>{{ number_format($ga4Data['organic_sessions'] ?? 0) }}</td></tr>
                                                <tr><td>{{ __('Organik olmayan oturum') }}</td><td>{{ number_format(max(0, ($ga4Data['total_sessions'] ?? 0) - ($ga4Data['organic_sessions'] ?? 0))) }}</td></tr>
                                                @if ($ga4ChannelGroups = \App\Services\Ga4\Ga4ChannelGroups::group($ga4Data['channel_sessions'] ?? null))
                                                    <tr><td class="ps-4 text-secondary">{{ __('Doğrudan (Direct)') }}</td><td>{{ number_format($ga4ChannelGroups['direct']) }}</td></tr>
                                                    <tr><td class="ps-4 text-secondary">{{ __('Yönlendirme (Referral)') }}</td><td>{{ number_format($ga4ChannelGroups['referral']) }}</td></tr>
                                                    <tr><td class="ps-4 text-secondary">{{ __('Sosyal (Social)') }}</td><td>{{ number_format($ga4ChannelGroups['social']) }}</td></tr>
                                                    <tr><td class="ps-4 text-secondary">{{ __('Ücretli (Paid)') }}</td><td>{{ number_format($ga4ChannelGroups['paid']) }}</td></tr>
                                                    <tr><td class="ps-4 text-secondary">{{ __('Diğer kanallar') }}</td><td>{{ number_format($ga4ChannelGroups['other']) }}</td></tr>
                                                @endif
                                                <tr><td>{{ __('Aktif kullanıcı') }}</td><td>{{ number_format($ga4Data['active_users'] ?? 0) }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">{{ __('Property ID:') }} <code>{{ $ga4Data['property_id'] }}</code></p>
                                @endif
                                @php
                                    $ga4Properties = session('ga4Properties', []);
                                    $ga4PropertiesByAccount = collect($ga4Properties)->groupBy('accountName');
                                    $selectedGa4Property = collect($ga4Properties)->firstWhere('propertyId', $domain->ga4_property_id);
                                    $selectedGa4Account = $selectedGa4Property['accountName'] ?? null;
                                @endphp
                                @if ($canManage)
                                <form method="post" action="{{ route('domains.ga4-property.update', $domain) }}" class="d-flex gap-2 align-items-end mt-3">
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex-grow-1">
                                        @if (!empty($ga4Properties))
                                            <label class="form-label">{{ __('GA4 Hesabı') }}</label>
                                            <select class="form-select mb-2" data-ga4-account-select="{{ $domain->id }}">
                                                <option value="">{{ __('— Hesap seçiniz —') }}</option>
                                                @foreach ($ga4PropertiesByAccount as $accountName => $accountProperties)
                                                    <option value="{{ $accountName }}" @selected($selectedGa4Account === $accountName)>{{ \App\Support\StoredText::t($accountName) }} ({{ $accountProperties->count() }})</option>
                                                @endforeach
                                            </select>
                                            <label class="form-label">GA4 Property</label>
                                            <select name="ga4_property_id" class="form-select" data-ga4-property-select="{{ $domain->id }}" @if(!$selectedGa4Account) disabled @endif>
                                                <option value="">{{ $selectedGa4Account ? __('— Seçiniz —') : __('— Önce hesap seçin —') }}</option>
                                                @foreach ($ga4Properties as $property)
                                                    <option
                                                        value="{{ $property['propertyId'] }}"
                                                        data-account="{{ $property['accountName'] }}"
                                                        @selected($domain->ga4_property_id === $property['propertyId'])
                                                        @if($selectedGa4Account !== $property['accountName']) hidden @endif
                                                    >{{ $property['label'] }} ({{ $property['propertyId'] }})</option>
                                                @endforeach
                                            </select>
                                            <small class="form-hint">{{ __('Önce hesabınızı, ardından property\'nizi seçin. :accountCount hesap ve :propertyCount property bulundu.', ['accountCount' => $ga4PropertiesByAccount->count(), 'propertyCount' => count($ga4Properties)]) }}</small>
                                        @else
                                            <label class="form-label">GA4 Property ID</label>
                                            <input type="text" name="ga4_property_id" class="form-control" value="{{ $domain->ga4_property_id }}" placeholder="{{ __('ör. 123456789') }}">
                                            <small class="form-hint">{{ __("GA4 Yönetici paneli &rarr; Mülk ayarları'ndan kopyalayın, ya da aşağıdan listeyi getirin.") }}</small>
                                        @endif
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary">{{ __('Kaydet') }}</button>
                                </form>
                                @if (empty($ga4Properties) && $domain->user?->hasGoogleOfflineAccess())
                                    <form method="post" action="{{ route('domains.ga4-properties.fetch', $domain) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="ti ti-refresh icon"></i> {{ __('Property listesini getir') }}
                                        </button>
                                    </form>
                                @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-bing">
                                {{ __('Backlink (Bing Webmaster)') }}
                            </button>
                        </h2>
                        <div id="ac-bing" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if (!$domain->user?->hasBingOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        {!! __('Bing hesabınız bağlı değil. Backlink verisi için üstteki ":link" bağlantısından bağlanın.', ['link' => __('Bing hesabını bağla')]) !!}
                                    </p>
                                @elseif ($bingData['error'] ?? null)
                                    <p class="text-danger mb-0">{{ __('Bing Webmaster sorgulanırken hata oluştu: :error', ['error' => \App\Support\StoredText::t($bingData['error'])]) }}</p>
                                @elseif (!($bingData['verified'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {{ __('Bu domain, bağlı Bing hesabınızda doğrulanmış bir site olarak bulunamadı. Not: bu, genel bir backlink indeksi değildir — yalnızca kendi Bing Webmaster hesabınızda doğruladığınız siteler için veri döner.') }}
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>{{ __('Sayfa') }}</th><th>{{ __('Inbound link sayısı') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($bingData['top_pages'] ?? [] as $page)
                                                <tr>
                                                    <td class="text-truncate" style="max-width: 420px;">
                                                        <a href="{{ $page['url'] }}" target="_blank" rel="noopener">{{ $page['url'] }}</a>
                                                    </td>
                                                    <td>{{ $page['count'] }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        {{ __('Toplam :total inbound link, :pages sayfaya dağılmış. Mülk: :siteUrl', ['total' => number_format($bingData['total_links'] ?? 0), 'pages' => number_format($bingData['pages_with_links'] ?? 0), 'siteUrl' => $bingData['site_url']]) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-secondary mb-0">{{ __('Henüz site kontrolü çalıştırılmadı.') }}</p>
            @endif
        </div>
    </div>
    </div>

    <div class="tab-pane" id="tab-indexnow" role="tabpanel">
        @include('domains.partials.indexnow')
    </div>

    <div class="tab-pane" id="tab-ai" role="tabpanel">
        @include('domains.partials.aio-stability', ['aioStability' => $aioStability])

        @if (auth()->user()->is_admin)
        @include('domains.partials.prompt-explorer-form')
        @endif

        @include('domains.partials.prompt-history')

        @include('domains.partials.llm-mentions')

        @if (auth()->user()->is_admin)

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('AI Görünürlük Kontrolü (ChatGPT/Claude/Gemini/Perplexity)') }}</h3>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('domains.llm-visibility.update', $domain) }}" class="mb-4">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="llm_visibility_enabled" value="{{ $domain->llm_visibility_enabled ? '0' : '1' }}">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" onchange="this.form.submit()" {{ $domain->llm_visibility_enabled ? 'checked' : '' }}>
                        <span class="form-check-label">{{ __('Bu domain için AI görünürlük kontrolü aktif') }}</span>
                    </label>
                </form>

                <form method="post" action="{{ route('domains.llm-scraper.update', $domain) }}" class="mb-4">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="llm_scraper_enabled" value="{{ $domain->llm_scraper_enabled ? '0' : '1' }}">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" onchange="this.form.submit()" {{ $domain->llm_scraper_enabled ? 'checked' : '' }} {{ !$dataForSeoConfigured && !$domain->llm_scraper_enabled ? 'disabled' : '' }}>
                        <span class="form-check-label">{{ __('ChatGPT ve Gemini web arayüzü cevabını da kontrol et (DataForSEO LLM Scraper)') }}</span>
                    </label>
                    <div class="form-hint">
                        {{ __('API cevabı yerine kullanıcının chatgpt.com ve gemini.google.com\'da gördüğü cevabı, web araması ve kaynak linkleriyle birlikte ölçer. API key gerekmez; maliyet uygulamanın DataForSEO hesabından çıkar.') }}
                        @if (!$dataForSeoConfigured)
                            <span class="text-warning">{{ __('DataForSEO yapılandırılmamış (DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD).') }}</span>
                        @endif
                    </div>
                </form>

                <h4 class="mb-3">{{ __('API Key\'ler') }}</h4>
                @if ($domain->llmApiKeys->isEmpty())
                    <p class="text-secondary small">{{ __('Henüz hiçbir sağlayıcı için API key girilmedi.') }}</p>
                @else
                    <table class="table table-vcenter mb-4">
                        <thead>
                            <tr>
                                <th>{{ __('Sağlayıcı') }}</th>
                                <th>{{ __('Etiket') }}</th>
                                <th>{{ __('Key') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($domain->llmApiKeys as $llmApiKey)
                                <tr>
                                    <td>{{ \App\Models\DomainLlmApiKey::PROVIDER_LABELS[$llmApiKey->provider] ?? $llmApiKey->provider }}</td>
                                    <td><span class="badge bg-{{ $llmApiKey->label === 'bireysel' ? 'blue-lt' : 'green-lt' }}">{{ $llmApiKey->label }}</span></td>
                                    <td><code>{{ $llmApiKey->maskedKey() }}</code></td>
                                    <td class="text-end">
                                        <form method="post" action="{{ route('domains.llm-api-keys.destroy', [$domain, $llmApiKey]) }}" onsubmit="return confirm({{ json_encode(__('Bu API key silinsin mi?')) }});">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Sil') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <form method="post" action="{{ route('domains.llm-api-keys.store', $domain) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label class="form-label">{{ __('Sağlayıcı') }}</label>
                        <select name="provider" class="form-select" required>
                            @foreach (\App\Models\DomainLlmApiKey::PROVIDERS as $provider)
                                <option value="{{ $provider }}">{{ \App\Models\DomainLlmApiKey::PROVIDER_LABELS[$provider] ?? $provider }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label">{{ __('Etiket') }}</label>
                        <select name="label" class="form-select" required>
                            @foreach (\App\Models\DomainLlmApiKey::LABELS as $label)
                                <option value="{{ $label }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label">{{ __('API Key') }}</label>
                        <input type="text" name="api_key" class="form-control" placeholder="sk-..." required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">{{ __('Kaydet') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    @if ($sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > 0)
    <div class="tab-pane" id="tab-pages" role="tabpanel">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('Keşfedilen Sayfalar (Sitemap)') }}</h3>
                <div class="card-actions">
                    <span class="badge bg-success-lt">{{ __(":count sitemap'te", ['count' => $sitemapUrlCounts['active']]) }}</span>
                    @if ($sitemapUrlCounts['removed'] > 0)
                        <span class="badge bg-warning-lt">{{ __(':count kaldırıldı', ['count' => $sitemapUrlCounts['removed']]) }}</span>
                    @endif
                    <a href="{{ route('domains.lighthouse-report', $domain) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-gauge icon"></i> {{ __('Sayfa Raporu') }}
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                <p class="text-secondary mb-0">
                    {{ __('Sitemap.xml\'de bulunan sayfalar; her "Site Kontrolü Yap" çalıştırmasında yeniden okunur. Bir sayfa sitemap\'ten kaldırılırsa (silinmez, sadece) "kaldırıldı" olarak işaretlenir.') }}
                </p>
            </div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table card-table table-vcenter table-sm">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>{{ __('İlk görülme') }}</th>
                            <th>{{ __('Son görülme') }}</th>
                            <th>{{ __('Durum') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sitemapUrls as $sitemapUrl)
                            <tr>
                                <td class="text-truncate" style="max-width: 420px;">
                                    <a href="{{ $sitemapUrl->url }}" target="_blank" rel="noopener">{{ $sitemapUrl->url }}</a>
                                </td>
                                <td class="text-secondary">{{ $sitemapUrl->first_seen_at->format('Y-m-d') }}</td>
                                <td class="text-secondary">{{ $sitemapUrl->last_seen_at->format('Y-m-d') }}</td>
                                <td>
                                    @if ($sitemapUrl->removed_at)
                                        <span class="badge bg-warning-lt">{{ __('kaldırıldı: :date', ['date' => $sitemapUrl->removed_at->format('Y-m-d')]) }}</span>
                                    @else
                                        <span class="badge bg-success-lt">{{ __("sitemap'te") }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > $sitemapUrls->count())
                <div class="card-body py-2">
                    <p class="text-secondary mb-0 small">{{ __('İlk :count kayıt gösteriliyor.', ['count' => $sitemapUrls->count()]) }}</p>
                </div>
            @endif
        </div>
    </div>

    @endif

    <div class="tab-pane" id="tab-competitors" role="tabpanel">
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Rakip Analizi') }}</h3>
        </div>
        <div class="card-body">
            @if ($competitorAnalysis['tracked_keyword_count'] === 0)
                <p class="text-secondary mb-0">{{ __('Rakip analizi için en az bir anahtar kelimede engellenmemiş bir SERP kontrolü gerekir.') }}</p>
            @elseif (empty($competitorAnalysis['competitors']))
                <p class="text-secondary mb-0">{{ __('İzlenen anahtar kelimelerin SERP sonuçlarında tekrar eden bir rakip domain bulunamadı.') }}</p>
            @else
                <p class="text-secondary">
                    {{ __('İzlenen :count anahtar kelimenin son SERP sonuçlarında sizinle birlikte en sık çıkan domainler:', ['count' => $competitorAnalysis['tracked_keyword_count']]) }}
                </p>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>{{ __('Ortak anahtar kelime') }}</th>
                                <th>{{ __('Ortalama pozisyon') }}</th>
                                <th>{{ __('En iyi pozisyon') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($competitorAnalysis['competitors'] as $competitor)
                                <tr>
                                    <td class="fw-medium">{{ $competitor['domain'] }}</td>
                                    <td>
                                        <span
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="{{ implode(', ', $competitor['keywords']) }}"
                                            style="cursor: help; border-bottom: 1px dotted currentColor;"
                                        >{{ __(':count / :total', ['count' => $competitor['keyword_count'], 'total' => $competitorAnalysis['tracked_keyword_count']]) }}</span>
                                    </td>
                                    <td class="text-secondary">{{ $competitor['average_position'] }}</td>
                                    <td class="text-secondary">{{ $competitor['best_position'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

        @include('domains.partials.share-of-model', ['shareOfModel' => $shareOfModel])
    </div>

    <div class="tab-pane" id="tab-history" role="tabpanel">
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Geçmiş Skorlar') }}</h3>
        </div>
        <div class="card-body">
            @php
                $aiSnapshot = $domain->aiVisibilitySnapshot();
            @endphp
            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <div class="text-secondary small mb-1">{{ __('AI Overview Görünürlük Skoru') }}</div>
                    @if ($aiSnapshot['score'] === null)
                        <div class="h2 mb-1 text-secondary">—</div>
                        <div class="text-secondary small">{{ __('Henüz hiçbir anahtar kelimede AI Overview gözlemlenmedi.') }}</div>
                    @else
                        <div class="h2 mb-1">{{ number_format($aiSnapshot['score'], 0) }}%</div>
                        <div class="text-secondary small">{{ __(':cited / :present anahtar kelimede AI Overview kaynağında domain gösteriliyor', ['cited' => $aiSnapshot['cited_count'], 'present' => $aiSnapshot['present_count']]) }}</div>
                    @endif
                </div>
            </div>

            @if (empty($scoreHistory['labels']))
                <p class="text-secondary mb-0">{{ __('Grafikler için tamamlanmış en az bir kontrol gerekir.') }}</p>
            @else
                <div class="row row-cards">
                    <div class="col-12 col-lg-4">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('AI Overview görünürlük trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[['label' => __('Görünürlük'), 'color' => '#34A853', 'values' => $scoreHistory['ai_visibility']]]"
                                    :min="0"
                                    :max="100"
                                    value-suffix="%"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Lighthouse skor trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[
                                        ['label' => __('Performans'), 'color' => '#4285F4', 'values' => $scoreHistory['lighthouse_performance']],
                                        ['label' => 'SEO', 'color' => '#34A853', 'values' => $scoreHistory['lighthouse_seo']],
                                        ['label' => __('Erişilebilirlik'), 'color' => '#FBBC04', 'values' => $scoreHistory['lighthouse_accessibility']],
                                        ['label' => 'Best Practices', 'color' => '#EA4335', 'values' => $scoreHistory['lighthouse_best_practices']],
                                    ]"
                                    :min="0"
                                    :max="100"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Ortalama SERP pozisyon trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[['label' => __('Pozisyon'), 'color' => '#4285F4', 'values' => $scoreHistory['average_position']]]"
                                    :min="1"
                                    :invert-y="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @unless (empty($domainCheckHistory['labels']))
                <hr class="my-4">
                <h4 class="mb-3">{{ __('Site Kontrolü Geçmişi') }}</h4>
                <div class="row row-cards mb-3">
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('CrUX yükleme süresi trendi (ms)') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[
                                        ['label' => 'LCP', 'color' => '#4285F4', 'values' => $domainCheckHistory['crux_lcp']],
                                        ['label' => 'FCP', 'color' => '#34A853', 'values' => $domainCheckHistory['crux_fcp']],
                                        ['label' => 'TTFB', 'color' => '#FBBC04', 'values' => $domainCheckHistory['crux_ttfb']],
                                        ['label' => 'INP', 'color' => '#EA4335', 'values' => $domainCheckHistory['crux_inp']],
                                    ]"
                                    :min="0"
                                    value-suffix="ms"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('CrUX CLS trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => 'CLS', 'color' => '#EA4335', 'values' => $domainCheckHistory['crux_cls']]]"
                                    :min="0"
                                    :decimals="3"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-cards mb-3">
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Search Console tıklama trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => __('Tıklama'), 'color' => '#4285F4', 'values' => $domainCheckHistory['gsc_clicks']]]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Search Console gösterim trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => __('Gösterim'), 'color' => '#34A853', 'values' => $domainCheckHistory['gsc_impressions']]]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">CTR {{ __('trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => 'CTR', 'color' => '#FBBC04', 'values' => $domainCheckHistory['gsc_ctr']]]"
                                    :min="0"
                                    value-suffix="%"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Search Console ortalama pozisyon trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => __('Pozisyon'), 'color' => '#4285F4', 'values' => $domainCheckHistory['gsc_average_position']]]"
                                    :min="1"
                                    :invert-y="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-cards">
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('GA4 oturum ve kullanıcı trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[
                                        ['label' => __('Toplam oturum'), 'color' => '#4285F4', 'values' => $domainCheckHistory['ga4_total_sessions']],
                                        ['label' => __('Organik oturum'), 'color' => '#34A853', 'values' => $domainCheckHistory['ga4_organic_sessions']],
                                        ['label' => __('Organik olmayan oturum'), 'color' => '#FBBC04', 'values' => $domainCheckHistory['ga4_non_organic_sessions']],
                                        ['label' => __('Aktif kullanıcı'), 'color' => '#EA4335', 'values' => $domainCheckHistory['ga4_active_users']],
                                    ]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('GA4 kanal bazında oturum trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[
                                        ['label' => __('Organik oturum'), 'color' => '#34A853', 'values' => $domainCheckHistory['ga4_organic_sessions']],
                                        ['label' => __('Doğrudan (Direct)'), 'color' => '#4285F4', 'values' => $domainCheckHistory['ga4_direct_sessions']],
                                        ['label' => __('Yönlendirme (Referral)'), 'color' => '#FBBC04', 'values' => $domainCheckHistory['ga4_referral_sessions']],
                                        ['label' => __('Sosyal (Social)'), 'color' => '#EA4335', 'values' => $domainCheckHistory['ga4_social_sessions']],
                                        ['label' => __('Ücretli (Paid)'), 'color' => '#185ABC', 'values' => $domainCheckHistory['ga4_paid_sessions']],
                                        ['label' => __('Diğer kanallar'), 'color' => '#667382', 'values' => $domainCheckHistory['ga4_other_sessions']],
                                    ]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            @endunless
        </div>
    </div>
    </div>

    <div class="tab-pane" id="tab-info" role="tabpanel">
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">WHOIS</h3>
            @if ($canEdit)
            <div class="card-actions">
                <form method="post" action="{{ route('domains.whois.refresh', $domain) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-refresh icon"></i> {{ __('WHOIS Bilgisini Yenile') }}
                    </button>
                </form>
            </div>
            @endif
        </div>
        <div class="card-body">
            @if (!$domain->fact?->whois_checked_at)
                <p class="text-secondary mb-0">{{ __('WHOIS bilgisi henüz kuyruğa alınmadı ya da worker tarafından işlenmedi.') }}</p>
            @elseif ($domain->fact->whois_error)
                <p class="text-danger mb-0">{{ __('WHOIS sorgusu başarısız: :error', ['error' => \App\Support\StoredText::t($domain->fact->whois_error)]) }}</p>
            @else
                <div class="row">
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small mb-1">{{ __('Domain Yaşı') }}</div>
                        <div class="h3 mb-0">
                            @if ($domain->whoisAgeInYears() !== null)
                                {{ __(':years yıl', ['years' => $domain->whoisAgeInYears()]) }}
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </div>
                        @if ($domain->fact->whois_registered_at)
                            <div class="text-secondary small">{{ __('Kayıt tarihi:') }} {{ $domain->fact->whois_registered_at->format('Y-m-d') }}</div>
                        @endif
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small mb-1">{{ __('Registrar') }}</div>
                        <div class="h3 mb-0">{{ $domain->fact->whois_registrar ?: '—' }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small mb-1">{{ __('Kayıt Süresi Sonu') }}</div>
                        <div class="h3 mb-0">{{ $domain->fact->whois_expires_at?->format('Y-m-d') ?: '—' }}</div>
                    </div>
                </div>
                <div class="text-secondary small mt-3">{{ __('Son WHOIS kontrolü: :date', ['date' => $domain->fact->whois_checked_at->format('Y-m-d H:i:s')]) }}</div>
            @endif
        </div>
    </div>
    </div>

    </div>

    <div class="modal modal-blur fade" id="modal-add-keyword" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="{{ route('keywords.store', $domain) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Anahtar Kelime Ekle') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Anahtar kelime') }}</label>
                            <input type="text" name="keyword" class="form-control" required autofocus>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">{{ __('On-page analiz sayfası (opsiyonel)') }}</label>
                            <input type="url" name="url" class="form-control" placeholder="https://{{ $domain->domain }}/sayfa">
                            <small class="form-hint">{{ __('Boş bırakılırsa :url kullanılır.', ['url' => 'https://' . $domain->domain . '/']) }}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('İptal') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-plus icon"></i> {{ __('Ekle') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if ($canEdit)
        @include('domains.partials.share-modal')
    @endif
    @if ($canManage)
        @include('domains.partials.members-modal')
    @endif
@endsection
