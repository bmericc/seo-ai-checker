@if (config('seo.google_login_enabled'))
    <a href="{{ route('google.redirect') }}" class="btn btn-outline-secondary w-100">
        <i class="ti ti-brand-google icon me-1" aria-hidden="true"></i>{{ __('Google ile giriş yap') }}
    </a>
    <div class="hr-text">{{ __('veya') }}</div>
@endif
