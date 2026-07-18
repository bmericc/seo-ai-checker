@extends('layout')

@section('title', __('Sayfa Raporu') . ' — ' . $domain->domain)

@section('page-pretitle')
    <a href="{{ route('domains.show', $domain) }}" class="text-secondary">&larr; {{ $domain->domain }}</a>
@endsection
@section('page-title', __('Sayfa Raporu'))
@section('page-actions')
    <form method="post" action="{{ route('domains.lighthouse-report.start-onpage', $domain) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-primary">
            <i class="ti ti-file-search icon"></i> {{ __(':count Sayfa Analiz Et', ['count' => $maxOnPageUrlsPerBatch]) }}
        </button>
    </form>
    <form method="post" action="{{ route('domains.lighthouse-report.start', $domain) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-gauge icon"></i> {{ __(':count Sayfa Lighthouse Tara', ['count' => $maxLighthouseUrlsPerBatch]) }}
        </button>
    </form>
@endsection

@section('content')
    @if ($sitemapUrls->isEmpty())
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-gauge fs-1"></i></div>
                <p class="empty-title">{{ __("Henüz sitemap'te sayfa yok") }}</p>
                <p class="empty-subtitle text-secondary">{{ __('Önce domain sayfasında "Site Kontrolü Yap" ile sitemap taraması çalıştırın.') }}</p>
            </div>
        </div>
    @else
        <h2>{{ __('On-page SEO analizi') }}</h2>
        <p class="text-secondary">
            {{ __("Sitemap.xml'den keşfedilen sayfaların HTML'ini tarayıp canonical self-reference, H1/başlık hiyerarşisi, Open Graph + Twitter Card etiketleri ve Schema.org yapısal veri tiplerini kontrol eder. PSI'nin aksine hızlıdır (saniyeler), bu yüzden tek seferde daha fazla sayfa taranabilir.") }}
        </p>

        <div class="row row-cards mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Tarandı') }}</div>
                        <div class="h2 mb-0 text-success">{{ $onPageSummary['checked'] }} / {{ $onPageSummary['total'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Canonical eksik') }}</div>
                        <div class="h2 mb-0 {{ $onPageSummary['missing_canonical'] > 0 ? 'text-warning' : '' }}">{{ $onPageSummary['missing_canonical'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">H1 {{ __('sayısı') }} ≠ 1</div>
                        <div class="h2 mb-0 {{ $onPageSummary['wrong_h1_count'] > 0 ? 'text-warning' : '' }}">{{ $onPageSummary['wrong_h1_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Open Graph eksik') }}</div>
                        <div class="h2 mb-0 {{ $onPageSummary['missing_og'] > 0 ? 'text-warning' : '' }}">{{ $onPageSummary['missing_og'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('hreflang sorunu') }}</div>
                        <div class="h2 mb-0 {{ $onPageSummary['hreflang_issues'] > 0 ? 'text-warning' : '' }}">{{ $onPageSummary['hreflang_issues'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Görsel sorunu olan sayfa') }}</div>
                        <div class="h2 mb-0 {{ $onPageSummary['image_issues'] > 0 ? 'text-warning' : '' }}">{{ $onPageSummary['image_issues'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Kuyrukta') }}</div>
                        <div class="h2 mb-0 {{ $onPageSummary['queued'] > 0 ? 'text-info' : 'text-secondary' }}">{{ $onPageSummary['queued'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('Sayfa listesi (:count)', ['count' => $sitemapUrls->count()]) }}</h3>
                <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#onpage-table">
                        {{ __('Göster / gizle') }}
                    </button>
                </div>
            </div>
            <div class="collapse" id="onpage-table">
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Canonical</th>
                            <th>H1 / {{ __('Hiyerarşi') }}</th>
                            <th>Open Graph</th>
                            <th>Schema.org</th>
                            <th>hreflang</th>
                            <th>{{ __('Görseller') }}</th>
                            <th>{{ __('Son kontrol') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sitemapUrls as $url)
                            <tr>
                                <td class="text-truncate" style="max-width: 320px;">
                                    <a href="{{ $url->url }}" target="_blank" rel="noopener">{{ $url->url }}</a>
                                </td>
                                @if ($url->isOnPageQueued())
                                    <td colspan="6"><span class="badge bg-info-lt">{{ __('kuyrukta') }}</span></td>
                                @elseif ($url->onpage_error)
                                    <td colspan="6">
                                        <span class="badge bg-danger-lt">{{ __('hata') }}</span>
                                        <span class="text-secondary small">{{ $url->onpage_error }}</span>
                                    </td>
                                @elseif (!$url->isOnPageChecked())
                                    <td colspan="6"><span class="badge bg-secondary-lt">{{ __('bekliyor') }}</span></td>
                                @else
                                    @php
                                        $op = $url->onpage_data ?? [];
                                    @endphp
                                    <td>
                                        @if (($op['canonical_status'] ?? null) === 'self')
                                            <span class="badge bg-success-lt">{{ __('kendine işaret ediyor') }}</span>
                                        @elseif (($op['canonical_status'] ?? null) === 'different')
                                            <span class="badge bg-warning-lt">{{ __('farklı URL') }}</span>
                                        @else
                                            <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (($op['h1_count'] ?? 1) !== 1)
                                            <span class="badge bg-warning-lt">H1: {{ $op['h1_count'] ?? 0 }}</span>
                                        @else
                                            <span class="badge bg-success-lt">H1: 1</span>
                                        @endif
                                        @if ($op['heading_hierarchy_skip'] ?? false)
                                            <span class="badge bg-warning-lt">{{ __('atlama var') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $ogCount = collect($op['og_tags'] ?? [])->filter()->count();
                                        @endphp
                                        @if ($ogCount === 0)
                                            <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                        @else
                                            <span class="badge bg-success-lt">{{ __(':count/5 etiket', ['count' => $ogCount]) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $schemaTypes = $op['schema_types'] ?? [];
                                        @endphp
                                        @if (empty($schemaTypes))
                                            <span class="text-secondary">{{ __('yok') }}</span>
                                        @else
                                            @foreach ($schemaTypes as $type)
                                                <span class="badge {{ in_array($type, $op['deprecated_schema_types'] ?? []) ? 'bg-warning-lt' : 'bg-azure-lt' }}">{{ $type }}</span>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $hreflangTags = $op['hreflang_tags'] ?? [];
                                            $hreflangIssues = $op['hreflang_issues'] ?? [];
                                        @endphp
                                        @if (empty($hreflangTags))
                                            <span class="text-secondary">{{ __('yok') }}</span>
                                        @elseif (!empty($hreflangIssues))
                                            <span class="badge bg-warning-lt">{{ __(':count sorun', ['count' => count($hreflangIssues)]) }}</span>
                                        @else
                                            <span class="badge bg-success-lt">{{ __(':count dil', ['count' => count($hreflangTags)]) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $imgStats = $op['image_stats'] ?? null;
                                        @endphp
                                        @if ($imgStats === null || $imgStats['total'] === 0)
                                            <span class="text-secondary">-</span>
                                        @else
                                            {{ $imgStats['total'] }}
                                            @if ($imgStats['missing_alt'] > 0)
                                                <span class="badge bg-warning-lt">{{ __(':count alt eksik', ['count' => $imgStats['missing_alt']]) }}</span>
                                            @endif
                                            @if ($imgStats['missing_dimensions'] > 0)
                                                <span class="badge bg-warning-lt">{{ __(':count boyut eksik', ['count' => $imgStats['missing_dimensions']]) }}</span>
                                            @endif
                                        @endif
                                    </td>
                                @endif
                                <td class="text-secondary">{{ $url->onpage_checked_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>
        </div>

        <h2>Lighthouse (PageSpeed Insights)</h2>
        <p class="text-secondary">
            {{ __('Her tıklama en fazla :count sayfayı kuyruğa ekler — önce hiç taranmamış, sonra en eski taranmış sayfalar öncelikli seçilir. Sayfa başına 15-40 saniye sürebilir.', ['count' => $maxLighthouseUrlsPerBatch]) }}
        </p>

        <div class="row row-cards mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Tarandı') }}</div>
                        <div class="h2 mb-0 text-success">{{ $lighthouseSummary['checked'] }} / {{ $lighthouseSummary['total'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Bekliyor') }}</div>
                        <div class="h2 mb-0 text-secondary">{{ $lighthouseSummary['pending'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Kuyrukta') }}</div>
                        <div class="h2 mb-0 {{ $lighthouseSummary['queued'] > 0 ? 'text-info' : 'text-secondary' }}">{{ $lighthouseSummary['queued'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Hata') }}</div>
                        <div class="h2 mb-0 {{ $lighthouseSummary['failed'] > 0 ? 'text-danger' : '' }}">{{ $lighthouseSummary['failed'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">{{ __('Ort. Performans') }}</div>
                        <div class="h2 mb-0">{{ $lighthouseSummary['checked'] > 0 ? $lighthouseSummary['avg_performance'] : '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Sayfa listesi (:count)', ['count' => $sitemapUrls->count()]) }}</h3>
                <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#lighthouse-table">
                        {{ __('Göster / gizle') }}
                    </button>
                </div>
            </div>
            <div class="collapse" id="lighthouse-table">
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>{{ __('Performans') }}</th>
                            <th>SEO</th>
                            <th>{{ __('Erişilebilirlik') }}</th>
                            <th>Best Practices</th>
                            <th>{{ __('Son kontrol') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sitemapUrls as $url)
                            @php
                                $scoreBadge = function (?int $score) {
                                    if ($score === null) {
                                        return '<span class="text-secondary">-</span>';
                                    }
                                    $class = $score >= 90 ? 'bg-success-lt' : ($score >= 50 ? 'bg-warning-lt' : 'bg-danger-lt');
                                    return '<span class="badge ' . $class . '">' . $score . '</span>';
                                };
                            @endphp
                            <tr>
                                <td class="text-truncate" style="max-width: 420px;">
                                    <a href="{{ $url->url }}" target="_blank" rel="noopener">{{ $url->url }}</a>
                                </td>
                                @if ($url->isLighthouseQueued())
                                    <td colspan="4"><span class="badge bg-info-lt">{{ __('kuyrukta') }}</span></td>
                                @elseif ($url->lighthouse_error)
                                    <td colspan="4">
                                        <span class="badge bg-danger-lt">{{ __('hata') }}</span>
                                        <span class="text-secondary small">{{ $url->lighthouse_error }}</span>
                                    </td>
                                @elseif (!$url->isLighthouseChecked())
                                    <td colspan="4"><span class="badge bg-secondary-lt">{{ __('bekliyor') }}</span></td>
                                @else
                                    <td>{!! $scoreBadge($url->lighthouse_performance) !!}</td>
                                    <td>{!! $scoreBadge($url->lighthouse_seo) !!}</td>
                                    <td>{!! $scoreBadge($url->lighthouse_accessibility) !!}</td>
                                    <td>{!! $scoreBadge($url->lighthouse_best_practices) !!}</td>
                                @endif
                                <td class="text-secondary">{{ $url->lighthouse_checked_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    @endif
@endsection
