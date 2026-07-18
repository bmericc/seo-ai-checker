@extends('layout-center')

@section('title', config('app.name'))
@section('container-class', 'container-narrow')

@section('content')
    <div class="text-center mb-4">
        <p class="text-secondary fs-3">
            {!! __('Google arama sonuçlarında sıralama takibi, Google <strong>AI Overview</strong> kutusunda görünürlük kontrolü, temel <strong>on-page SEO</strong> analizi ve <strong>Lighthouse</strong> (Google PageSpeed Insights) skorlarını bir araya getiren bir panel.') !!}
        </p>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-md-6">
            <div class="card card-sm">
                <div class="card-body d-flex align-items-center">
                    <span class="bg-primary text-white avatar me-3"><i class="ti ti-list-numbers"></i></span>
                    <div>
                        <div class="fw-bold">{{ __('SERP sıralaması') }}</div>
                        <div class="text-secondary">{{ __("Google'ın ilk ~20 organik sonucunu çeker, takip edilen domainin kaçıncı sırada çıktığını raporlar.") }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-sm">
                <div class="card-body d-flex align-items-center">
                    <span class="bg-primary text-white avatar me-3"><i class="ti ti-sparkles"></i></span>
                    <div>
                        <div class="fw-bold">{{ __('AI Overview kontrolü') }}</div>
                        <div class="text-secondary">{{ __('Sonuç sayfasında bir AI Overview kutusu olup olmadığını, varsa hangi domainlerin kaynak gösterildiğini raporlar.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-sm">
                <div class="card-body d-flex align-items-center">
                    <span class="bg-primary text-white avatar me-3"><i class="ti ti-file-search"></i></span>
                    <div>
                        <div class="fw-bold">{{ __('On-page SEO analizi') }}</div>
                        <div class="text-secondary">{{ __('Title/meta description uzunluğu, başlık yapısı, kelime yoğunluğu, eksik alt etiketleri, iç/dış link sayısı.') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-sm">
                <div class="card-body d-flex align-items-center">
                    <span class="bg-primary text-white avatar me-3"><i class="ti ti-gauge"></i></span>
                    <div>
                        <div class="fw-bold">{{ __('Lighthouse skorları') }}</div>
                        <div class="text-secondary">{{ __('Google PageSpeed Insights üzerinden performans, SEO, erişilebilirlik ve best-practices puanları.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center">
            <p class="text-secondary mb-3">
                {{ __('Bu panel herkese açık değildir; yalnızca site sahibi tarafından önceden izin verilmiş Google hesaplarıyla giriş yapılabilir. Erişiminiz varsa aşağıdan giriş yapabilirsiniz.') }}
            </p>
            <a href="{{ route('google.redirect') }}" class="btn btn-primary">
                <i class="ti ti-brand-google icon me-1"></i>{{ __('Google ile giriş yap') }}
            </a>
        </div>
    </div>
@endsection
