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
            <i class="ti ti-refresh icon"></i> Kontrol Et
        </button>
    </form>
@endsection

@section('content')
    <p class="text-secondary mb-4">Sayfa: {{ $keyword->targetUrl() }}</p>

    @if ($keyword->checks->isEmpty())
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-history fs-1"></i></div>
                <p class="empty-title">Henüz kontrol çalıştırılmadı</p>
            </div>
        </div>
    @else
        @foreach ($keyword->checks as $check)
            <div class="card mb-3">
                <div class="card-header flex-wrap gap-2">
                    <div class="me-auto fw-medium">{{ $check->created_at->format('Y-m-d H:i:s') }}</div>

                    @if ($check->blocked)
                        <span class="badge bg-warning-lt">Google engellendi</span>
                    @elseif ($check->target_position)
                        <span class="badge bg-success-lt">Sıralama: {{ $check->target_position }}</span>
                    @else
                        <span class="badge bg-secondary-lt">İlk sonuçlarda yok</span>
                    @endif

                    @if ($check->ai_overview_present)
                        @if ($check->ai_overview_target_cited)
                            <span class="badge bg-success-lt">AI Overview: kaynakta</span>
                        @else
                            <span class="badge bg-warning-lt">AI Overview: var, kaynakta değil</span>
                        @endif
                    @else
                        <span class="badge bg-secondary-lt">AI Overview yok</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($check->blocked)
                        <p class="text-danger">{{ $check->block_reason }}</p>
                    @endif

                    @if ($check->ai_overview_present)
                        <p class="text-secondary">
                            AI Overview kaynak domainler:
                            @if (!empty($check->ai_overview_cited_domains))
                                {{ implode(', ', $check->ai_overview_cited_domains) }}
                            @else
                                (otomatik çıkarılamadı){{ $check->ai_overview_note ? ' — ' . $check->ai_overview_note : '' }}
                            @endif
                        </p>
                    @endif

                    @if ($check->onpage_error)
                        <p class="text-danger mb-0">On-page analiz başarısız: {{ $check->onpage_error }}</p>
                    @endif

                    <div class="d-flex align-items-center gap-1 flex-wrap mt-2">
                        <span class="text-secondary me-1">Lighthouse:</span>
                        @if ($check->lighthouse_error)
                            <span class="text-danger">{{ $check->lighthouse_error }}</span>
                        @else
                            <span class="badge bg-blue-lt">Performans {{ $check->lighthouse_performance ?? '-' }}</span>
                            <span class="badge bg-blue-lt">SEO {{ $check->lighthouse_seo ?? '-' }}</span>
                            <span class="badge bg-blue-lt">Erişilebilirlik {{ $check->lighthouse_accessibility ?? '-' }}</span>
                            <span class="badge bg-blue-lt">Best Practices {{ $check->lighthouse_best_practices ?? '-' }}</span>
                        @endif
                    </div>

                    @if (!empty($check->organic_results) || $check->onpage || $check->lighthouse_raw)
                        <div class="accordion mt-3" id="check-accordion-{{ $check->id }}">
                            @if (!empty($check->organic_results))
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#organic-{{ $check->id }}">
                                            Organik sonuçlar ({{ count($check->organic_results) }})
                                        </button>
                                    </h2>
                                    <div id="organic-{{ $check->id }}" class="accordion-collapse collapse" data-bs-parent="#check-accordion-{{ $check->id }}">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-vcenter">
                                                    <thead><tr><th>#</th><th>Domain</th><th>Başlık</th></tr></thead>
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
                                            On-page SEO detayı
                                        </button>
                                    </h2>
                                    <div id="onpage-{{ $check->id }}" class="accordion-collapse collapse" data-bs-parent="#check-accordion-{{ $check->id }}">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-vcenter">
                                                    <tr><th class="w-25">Title</th><td>{{ $check->onpage['title'] ?: '(yok)' }} ({{ $check->onpage['title_length'] }} karakter)</td></tr>
                                                    <tr><th>Meta description</th><td>{{ $check->onpage['meta_description'] ?: '(yok)' }} ({{ $check->onpage['meta_description_length'] }} karakter)</td></tr>
                                                    <tr><th>H1</th><td>{{ !empty($check->onpage['h1s']) ? implode(', ', $check->onpage['h1s']) : '(yok)' }}</td></tr>
                                                    <tr><th>H2 sayısı</th><td>{{ $check->onpage['h2_count'] }}</td></tr>
                                                    <tr><th>Kelime sayısı</th><td>{{ $check->onpage['word_count'] }}</td></tr>
                                                    <tr><th>Anahtar kelime yoğunluğu</th><td>{{ $check->onpage['keyword_density_percent'] }}%</td></tr>
                                                    <tr><th>Alt etiketi eksik görsel</th><td>{{ $check->onpage['images_missing_alt'] }}</td></tr>
                                                    <tr><th>İç/dış link</th><td>{{ $check->onpage['internal_links'] }} / {{ $check->onpage['external_links'] }}</td></tr>
                                                    <tr><th>Yapısal veri</th><td>{{ $check->onpage['has_structured_data'] ? 'var' : 'yok' }}</td></tr>
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
                                            Lighthouse detayı (Core Web Vitals + ham veri)
                                        </button>
                                    </h2>
                                    <div id="lh-{{ $check->id }}" class="accordion-collapse collapse" data-bs-parent="#check-accordion-{{ $check->id }}">
                                        <div class="accordion-body">
                                            @if (!empty($metrics))
                                                <div class="table-responsive">
                                                    <table class="table table-vcenter">
                                                        <thead><tr><th>Metrik</th><th>Değer</th></tr></thead>
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
                                                <i class="ti ti-download icon"></i> Ham PSI JSON verisini indir
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
