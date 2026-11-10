@extends('layout')

@section('title', __('Bağlantılar'))
@section('page-title', __('Bağlantılar'))
@section('page-subtitle', __('Veri kaynaklarınızı tek yerden yönetin. Bağlantılar tüm domainleriniz için geçerlidir.'))

@section('content')
    @php
        $user = auth()->user();
        $hasGoogle = $user->hasGoogleOfflineAccess();
        $hasBing = $user->hasBingOfflineAccess();
    @endphp

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9 d-flex flex-column gap-3">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="avatar avatar-md bg-body-tertiary"><i class="ti ti-brand-google fs-2"></i></span>
                    <div class="flex-fill" style="min-width: 0; flex-basis: 320px">
                        <div class="fw-semibold">{{ __('Google — Search Console ve GA4') }}</div>
                        <div class="text-secondary small">{{ __('Tıklama, gösterim, sıralama ve ziyaretçi verisi.') }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                    @if ($hasGoogle)
                        <span class="badge bg-success-lt"><span class="badge badge-dot bg-success me-1"></span>{{ __('Bağlı') }}</span>
                        <a href="{{ route('google.redirect') }}" class="btn btn-outline-secondary">{{ __('Yeniden bağla') }}</a>
                    @else
                        <span class="badge bg-secondary-lt">{{ __('Bağlı değil') }}</span>
                        <a href="{{ route('google.redirect') }}" class="btn btn-dark">{{ __('Google hesabını bağla') }}</a>
                    @endif
                    </div>
                </div>
            </div>

            <div class="card {{ $hasBing ? '' : 'border-warning-subtle' }}">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="avatar avatar-md bg-body-tertiary"><i class="ti ti-brand-bing fs-2"></i></span>
                    <div class="flex-fill" style="min-width: 0; flex-basis: 320px">
                        <div class="fw-semibold">{{ __('Bing Webmaster Tools') }}</div>
                        <div class="text-secondary small">{{ __('Kendi doğruladığınız sitelerin backlink verisini görebilmek için Bing hesabınızı bağlamanız gerekiyor.') }}</div>
                        @if ($hasBing)
                            <div class="small text-warning mt-1">{{ __('Bing bağlantıyı zaman zaman sessizce düşürebiliyor. Backlink verisi gelmiyorsa yeniden bağlayın.') }}</div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                    @if ($hasBing)
                        <span class="badge bg-success-lt"><span class="badge badge-dot bg-success me-1"></span>{{ __('Bağlı') }}</span>
                        <a href="{{ route('bing.connect') }}" class="btn btn-outline-secondary">{{ __('Yeniden bağla') }}</a>
                    @else
                        <span class="badge bg-secondary-lt">{{ __('Bağlı değil') }}</span>
                        <a href="{{ route('bing.connect') }}" class="btn btn-dark">{{ __('Bing hesabını bağla') }}</a>
                    @endif
                    </div>
                </div>
            </div>

            @if ($user->is_admin)
                <div class="card">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <span class="avatar avatar-md bg-body-tertiary"><i class="ti ti-sparkles fs-2"></i></span>
                        <div class="flex-fill" style="min-width: 0; flex-basis: 320px">
                            <div class="fw-semibold">{{ __('Yapay zekâ görünürlük kontrolü') }}</div>
                            <div class="text-secondary small">{{ __('ChatGPT, Claude ve Gemini API anahtarları her domain için ayrı girilir: domain sayfasında "Yapay zekâ görünürlüğü" sekmesi.') }}</div>
                        </div>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">{{ __('Domainlere git') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
