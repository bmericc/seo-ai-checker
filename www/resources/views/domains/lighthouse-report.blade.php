@extends('layout')

@section('title', 'Lighthouse Raporu — ' . $domain->domain)

@section('page-pretitle')
    <a href="{{ route('domains.show', $domain) }}" class="text-secondary">&larr; {{ $domain->domain }}</a>
@endsection
@section('page-title', 'Lighthouse Raporu')
@section('page-actions')
    <form method="post" action="{{ route('domains.lighthouse-report.start', $domain) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-gauge icon"></i> {{ $maxUrlsPerBatch }} Sayfa Tara
        </button>
    </form>
@endsection

@section('content')
    <p class="text-secondary">
        Sitemap.xml'den keşfedilen sayfalar üzerinde Lighthouse (Google PageSpeed Insights) taramasını
        arka planda (kuyruk üzerinden) çalıştırır. Her tıklama en fazla {{ $maxUrlsPerBatch }} sayfayı
        kuyruğa ekler — önce hiç taranmamış, sonra en eski taranmış sayfalar öncelikli seçilir.
        Bir tarama, sayfa başına 15-40 saniye sürebilir; sonuçlar arka planda tamamlandıkça bu sayfayı
        yenileyerek görebilirsiniz.
    </p>

    <div class="row row-cards mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body text-center">
                    <div class="text-secondary small">Toplam sayfa</div>
                    <div class="h2 mb-0">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body text-center">
                    <div class="text-secondary small">Tarandı</div>
                    <div class="h2 mb-0 text-success">{{ $summary['checked'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body text-center">
                    <div class="text-secondary small">Bekliyor</div>
                    <div class="h2 mb-0 text-secondary">{{ $summary['pending'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body text-center">
                    <div class="text-secondary small">Hata</div>
                    <div class="h2 mb-0 {{ $summary['failed'] > 0 ? 'text-danger' : '' }}">{{ $summary['failed'] }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($summary['checked'] > 0)
        <div class="row row-cards mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">Ort. Performans</div>
                        <div class="h3 mb-0">{{ $summary['avg_performance'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">Ort. SEO</div>
                        <div class="h3 mb-0">{{ $summary['avg_seo'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">Ort. Erişilebilirlik</div>
                        <div class="h3 mb-0">{{ $summary['avg_accessibility'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body text-center">
                        <div class="text-secondary small">Ort. Best Practices</div>
                        <div class="h3 mb-0">{{ $summary['avg_best_practices'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        @if ($sitemapUrls->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-gauge fs-1"></i></div>
                <p class="empty-title">Henüz sitemap'te sayfa yok</p>
                <p class="empty-subtitle text-secondary">Önce domain sayfasında "Site Kontrolü Yap" ile sitemap taraması çalıştırın.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Performans</th>
                            <th>SEO</th>
                            <th>Erişilebilirlik</th>
                            <th>Best Practices</th>
                            <th>Son kontrol</th>
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
                                @if ($url->lighthouse_error)
                                    <td colspan="4">
                                        <span class="badge bg-danger-lt">hata</span>
                                        <span class="text-secondary small">{{ $url->lighthouse_error }}</span>
                                    </td>
                                @elseif (!$url->isLighthouseChecked())
                                    <td colspan="4"><span class="badge bg-secondary-lt">bekliyor</span></td>
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
        @endif
    </div>
@endsection
