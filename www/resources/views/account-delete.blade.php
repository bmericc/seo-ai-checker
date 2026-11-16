@extends('layout-center')

@section('title', __('Hesabı sil'))

@section('content')
    @php $email = auth()->user()->email; @endphp
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-3">{{ __('Hesabınızı silin') }}</h2>

            @if (!$canDelete)
                <div class="alert alert-warning mb-0" role="alert">
                    {{ __('Tek admin olduğunuz için hesabınızı silemezsiniz. Önce başka bir kullanıcıyı admin yapın.') }}
                </div>
            @else
                <p class="text-secondary">{{ __('Bu işlem geri alınamaz. Şunlar kalıcı olarak silinir:') }}</p>
                <ul class="text-secondary">
                    <li>{{ __('Hesabınız ve giriş bilgileriniz') }}</li>
                    <li>{{ __('Sahibi olduğunuz tüm domain\'ler, anahtar kelimeler ve kontrol geçmişi') }}</li>
                    <li>{{ __('Bağlı Google / Bing hesapları, mobil uygulama ve AI asistanı erişimleri') }}</li>
                    <li>{{ __('Size paylaşılan domain\'lere erişiminiz') }}</li>
                </ul>

                <form method="post" action="{{ route('account.destroy') }}" novalidate>
                    @csrf
                    @method('DELETE')
                    <div class="mb-3">
                        <label class="form-label" for="email">{{ __('Onaylamak için e-posta adresinizi yazın: :email', ['email' => $email]) }}</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" autocomplete="off" autocapitalize="none" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="ti ti-trash icon me-1"></i>{{ __('Hesabımı kalıcı olarak sil') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
    <div class="text-center text-secondary mt-3">
        <a href="{{ url('/') }}">{{ __('Vazgeç') }}</a>
    </div>
@endsection
