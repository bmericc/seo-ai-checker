@extends('layout-center')

@section('title', __('E-postanızı doğrulayın'))

@section('content')
    <div class="card card-md">
        <div class="card-body text-center py-4">
            <span class="avatar avatar-xl mb-3 bg-blue-lt"><i class="ti ti-mail-check fs-1" aria-hidden="true"></i></span>
            <h2 class="mb-2">{{ __('E-postanızı doğrulayın') }}</h2>
            <p class="text-secondary mb-4">
                {{ __(':email adresine bir doğrulama bağlantısı gönderdik. Bağlantıya tıkladıktan sonra hesabınız yönetici onayına düşer.', ['email' => auth()->user()->email]) }}
            </p>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                <form method="post" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-send icon me-1" aria-hidden="true"></i>{{ __('Bağlantıyı tekrar gönder') }}
                    </button>
                </form>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="ti ti-logout icon me-1" aria-hidden="true"></i>{{ __('Çıkış yap') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
