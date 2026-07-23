@extends('layout')

@section('title', $keyword->keyword)

@section('page-pretitle')
    <a href="{{ route('domains.show', $keyword->domain) }}" class="text-secondary">&larr; {{ $keyword->domain->domain }}</a>
@endsection
@section('page-title', $keyword->keyword)
@section('page-actions')
    <form method="post" action="{{ route('keywords.check', $keyword) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-refresh icon"></i> {{ __('Kontrol Et') }}
        </button>
    </form>
@endsection

@section('content')
    <p class="text-secondary mb-4">{{ __('Sayfa:') }} {{ $keyword->targetUrl() }}</p>

    @if (count($scoreHistory['labels']) > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('Geçmiş Skor Trendi') }}</h3>
            </div>
            <div class="card-body">
                <div class="row row-cards">
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Lighthouse skor trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[
                                        ['label' => __('Performans'), 'color' => '#206bc4', 'values' => $scoreHistory['lighthouse_performance']],
                                        ['label' => 'SEO', 'color' => '#2fb344', 'values' => $scoreHistory['lighthouse_seo']],
                                        ['label' => __('Erişilebilirlik'), 'color' => '#ae3ec9', 'values' => $scoreHistory['lighthouse_accessibility']],
                                        ['label' => 'Best Practices', 'color' => '#f76707', 'values' => $scoreHistory['lighthouse_best_practices']],
                                    ]"
                                    :min="0"
                                    :max="100"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('SERP pozisyon trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[['label' => __('Pozisyon'), 'color' => '#4263eb', 'values' => $scoreHistory['average_position']]]"
                                    :min="1"
                                    :invert-y="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($keyword->checks->isEmpty())
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-history fs-1"></i></div>
                <p class="empty-title">{{ __('Henüz kontrol çalıştırılmadı') }}</p>
            </div>
        </div>
    @else
        @foreach ($keyword->checks as $check)
            <div class="card mb-3">
                <div class="card-header flex-wrap gap-2">
                    <div class="me-auto fw-medium">{{ $check->created_at->format('Y-m-d H:i:s') }}</div>

                    @if ($check->blocked)
                        <span class="badge bg-warning-lt">{{ __('Google engellendi') }}</span>
                    @elseif ($check->target_position)
                        <span class="badge bg-success-lt">{{ __('Sıralama: :position', ['position' => $check->target_position]) }}</span>
                    @else
                        <span class="badge bg-secondary-lt">{{ __('İlk sonuçlarda yok') }}</span>
                    @endif

                    @if ($check->ai_overview_present)
                        @if ($check->ai_overview_target_cited)
                            <span class="badge bg-success-lt">{{ __('AI Overview: kaynakta') }}</span>
                        @else
                            <span class="badge bg-warning-lt">{{ __('AI Overview: var, kaynakta değil') }}</span>
                        @endif
                    @else
                        <span class="badge bg-secondary-lt">{{ __('AI Overview yok') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($check->blocked)
                        <p class="text-danger">{{ $check->block_reason }}</p>
                    @endif

                    @if ($check->ai_overview_present)
                        <p class="text-secondary">
                            {{ __('AI Overview kaynak domainler:') }}
                            @if (!empty($check->ai_overview_cited_domains))
                                {{ implode(', ', $check->ai_overview_cited_domains) }}
                            @else
                                {{ __('(otomatik çıkarılamadı)') }}{{ $check->ai_overview_note ? ' — ' . $check->ai_overview_note : '' }}
                            @endif
                        </p>
                    @endif

                    @if (!empty($check->llm_visibility))
                        <div class="mb-2">
                            <div class="text-secondary small mb-1">{{ __('AI Görünürlük Kontrolü (ChatGPT/Claude/Gemini)') }}</div>
                            <div class="d-flex flex-wrap gap-1 mb-1">
                                @foreach ($check->llm_visibility as $provider => $result)
                                    @if ($result['error'])
                                        <span class="badge bg-danger-lt" data-bs-toggle="tooltip" title="{{ $result['error'] }}">{{ $provider }}: {{ __('hata') }}</span>
                                    @elseif ($result['present'])
                                        <span class="badge bg-success-lt">{{ $provider }}: {{ __('geçiyor') }}</span>
                                    @else
                                        <span class="badge bg-secondary-lt">{{ $provider }}: {{ __('geçmiyor') }}</span>
                                    @endif
                                @endforeach
                            </div>
                            @foreach ($check->llm_visibility as $provider => $result)
                                @if ($result['response'])
                                    <div class="small text-secondary mb-1">
                                        <strong>{{ $provider }}:</strong> {{ \Illuminate\Support\Str::limit($result['response'], 220) }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if ($check->onpage_error)
                        <p class="text-danger mb-0">{{ __('On-page analiz başarısız: :error', ['error' => $check->onpage_error]) }}</p>
                    @endif

                    <div class="d-flex align-items-center gap-1 flex-wrap mt-2">
                        <span class="text-secondary me-1">Lighthouse:</span>
                        @if ($check->lighthouse_error)
                            <span class="text-danger">{{ $check->lighthouse_error }}</span>
                        @else
                            <span class="badge bg-blue-lt">{{ __('Performans') }} {{ $check->lighthouse_performance ?? '-' }}</span>
                            <span class="badge bg-blue-lt">SEO {{ $check->lighthouse_seo ?? '-' }}</span>
                            <span class="badge bg-blue-lt">{{ __('Erişilebilirlik') }} {{ $check->lighthouse_accessibility ?? '-' }}</span>
                            <span class="badge bg-blue-lt">Best Practices {{ $check->lighthouse_best_practices ?? '-' }}</span>
                        @endif
                    </div>

                    @if (!empty($check->organic_results) || $check->onpage || $check->lighthouse_raw)
                        <div class="accordion mt-3" id="check-accordion-{{ $check->id }}">
                            @if (!empty($check->organic_results))
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#organic-{{ $check->id }}">
                                            {{ __('Organik sonuçlar (:count)', ['count' => count($check->organic_results)]) }}
                                        </button>
                                    </h2>
                                    <div id="organic-{{ $check->id }}" class="accordion-collapse collapse" data-bs-parent="#check-accordion-{{ $check->id }}">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-vcenter">
                                                    <thead><tr><th>#</th><th>Domain</th><th>{{ __('Başlık') }}</th></tr></thead>
                                                    <tbody>
                                                    @foreach ($check->organic_results as $r)
                                                        <tr><td>{{ $r['position'] }}</td><td>{{ $r['domain'] }}</td><td>{{ $r['title'] }}</td></tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($check->onpage)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#onpage-{{ $check->id }}">
                                            {{ __('On-page SEO detayı') }}
                                        </button>
                                    </h2>
                                    <div id="onpage-{{ $check->id }}" class="accordion-collapse collapse" data-bs-parent="#check-accordion-{{ $check->id }}">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-vcenter">
                                                    <tr><th class="w-25">Title</th><td>{{ $check->onpage['title'] ?: __('(yok)') }} ({{ $check->onpage['title_length'] }} {{ __('karakter') }})</td></tr>
                                                    <tr><th>Meta description</th><td>{{ $check->onpage['meta_description'] ?: __('(yok)') }} ({{ $check->onpage['meta_description_length'] }} {{ __('karakter') }})</td></tr>
                                                    <tr><th>H1</th><td>{{ !empty($check->onpage['h1s']) ? implode(', ', $check->onpage['h1s']) : __('(yok)') }}</td></tr>
                                                    <tr><th>{{ __('H2 sayısı') }}</th><td>{{ $check->onpage['h2_count'] }}</td></tr>
                                                    <tr><th>{{ __('Kelime sayısı') }}</th><td>{{ $check->onpage['word_count'] }}</td></tr>
                                                    <tr><th>{{ __('Anahtar kelime yoğunluğu') }}</th><td>{{ $check->onpage['keyword_density_percent'] }}%</td></tr>
                                                    <tr><th>{{ __('Alt etiketi eksik görsel') }}</th><td>{{ $check->onpage['images_missing_alt'] }}</td></tr>
                                                    <tr><th>{{ __('İç/dış link') }}</th><td>{{ $check->onpage['internal_links'] }} / {{ $check->onpage['external_links'] }}</td></tr>
                                                    <tr>
                                                        <th>Canonical</th>
                                                        <td>
                                                            @php
                                                                $canonicalStatus = $check->onpage['canonical_status'] ?? null;
                                                            @endphp
                                                            @if ($canonicalStatus === 'self')
                                                                <span class="badge bg-success-lt">{{ __('kendine işaret ediyor') }}</span>
                                                            @elseif ($canonicalStatus === 'different')
                                                                <span class="badge bg-warning-lt">{{ __("farklı URL'e işaret ediyor") }}</span>
                                                            @elseif ($canonicalStatus === 'missing')
                                                                <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                                            @else
                                                                <span class="text-secondary">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>{{ __('Başlık hiyerarşisi') }}</th>
                                                        <td>
                                                            @if (($check->onpage['h1_count'] ?? count($check->onpage['h1s'] ?? [])) !== 1)
                                                                <span class="badge bg-warning-lt">{{ __('H1 sayısı: :count', ['count' => $check->onpage['h1_count'] ?? count($check->onpage['h1s'] ?? [])]) }}</span>
                                                            @endif
                                                            @if ($check->onpage['heading_hierarchy_skip'] ?? false)
                                                                <span class="badge bg-warning-lt">{{ __('seviye atlaması var') }}</span>
                                                            @elseif (isset($check->onpage['heading_hierarchy_skip']))
                                                                <span class="badge bg-success-lt">{{ __('sorun yok') }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Open Graph</th>
                                                        <td>
                                                            @php
                                                                $ogTags = collect($check->onpage['og_tags'] ?? [])->filter();
                                                            @endphp
                                                            @if ($ogTags->isEmpty())
                                                                <span class="text-secondary">{{ __('yok') }}</span>
                                                            @else
                                                                @foreach ($ogTags as $key => $value)
                                                                    <span class="badge bg-azure-lt">{{ $key }}</span>
                                                                @endforeach
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Twitter Card</th>
                                                        <td>{{ $check->onpage['twitter_card'] ?? '-' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>{{ __('Yapısal veri (Schema.org)') }}</th>
                                                        <td>
                                                            @php
                                                                $schemaTypes = $check->onpage['schema_types'] ?? ($check->onpage['has_structured_data'] ?? false ? [__('(tip tespit edilemedi)')] : []);
                                                            @endphp
                                                            @if (empty($schemaTypes))
                                                                <span class="text-secondary">{{ __('yok') }}</span>
                                                            @else
                                                                @foreach ($schemaTypes as $type)
                                                                    <span class="badge {{ in_array($type, $check->onpage['deprecated_schema_types'] ?? []) ? 'bg-warning-lt' : 'bg-azure-lt' }}">{{ $type }}</span>
                                                                @endforeach
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>hreflang</th>
                                                        <td>
                                                            @php
                                                                $hreflangTags = $check->onpage['hreflang_tags'] ?? [];
                                                                $hreflangIssues = $check->onpage['hreflang_issues'] ?? [];
                                                            @endphp
                                                            @if (empty($hreflangTags))
                                                                <span class="text-secondary">{{ __('yok') }}</span>
                                                            @else
                                                                @foreach (array_keys($hreflangTags) as $lang)
                                                                    <span class="badge bg-azure-lt">{{ $lang }}</span>
                                                                @endforeach
                                                                @foreach ($hreflangIssues as $issue)
                                                                    <div class="text-warning small">{{ $issue }}</div>
                                                                @endforeach
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>{{ __('Görseller') }}</th>
                                                        <td>
                                                            @php
                                                                $imgStats = $check->onpage['image_stats'] ?? null;
                                                            @endphp
                                                            @if ($imgStats === null)
                                                                <span class="text-secondary">-</span>
                                                            @else
                                                                {{ __(':count görsel', ['count' => $imgStats['total']]) }}
                                                                @if ($imgStats['missing_alt'] > 0)
                                                                    <span class="badge bg-warning-lt">{{ __(':count alt eksik', ['count' => $imgStats['missing_alt']]) }}</span>
                                                                @endif
                                                                @if ($imgStats['missing_dimensions'] > 0)
                                                                    <span class="badge bg-warning-lt">{{ __(':count boyut eksik', ['count' => $imgStats['missing_dimensions']]) }}</span>
                                                                @endif
                                                                @if ($imgStats['not_lazy'] > 0)
                                                                    <span class="badge bg-secondary-lt">{{ __(':count lazy-load değil', ['count' => $imgStats['not_lazy']]) }}</span>
                                                                @endif
                                                                @if ($imgStats['legacy_format'] > 0)
                                                                    <span class="badge bg-secondary-lt">{{ __(':count eski format (jpg/png/gif)', ['count' => $imgStats['legacy_format']]) }}</span>
                                                                @endif
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($check->lighthouse_raw)
                                @php $metrics = $check->lighthouseMetrics(); @endphp
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lh-{{ $check->id }}">
                                            {{ __('Lighthouse detayı (Core Web Vitals + ham veri)') }}
                                        </button>
                                    </h2>
                                    <div id="lh-{{ $check->id }}" class="accordion-collapse collapse" data-bs-parent="#check-accordion-{{ $check->id }}">
                                        <div class="accordion-body">
                                            @if (!empty($metrics))
                                                <div class="table-responsive">
                                                    <table class="table table-vcenter">
                                                        <thead><tr><th>{{ __('Metrik') }}</th><th>{{ __('Değer') }}</th></tr></thead>
                                                        <tbody>
                                                        @foreach ($metrics as $metric)
                                                            <tr>
                                                                <td>{{ $metric['label'] }}</td>
                                                                <td>{{ $metric['displayValue'] ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                            <a href="{{ route('checks.lighthouse-raw', $check) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="ti ti-download icon"></i> {{ __('Ham PSI JSON verisini indir') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
@endsection
