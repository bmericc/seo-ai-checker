@extends('layout-center')

@section('title', __('Kayıt ol'))

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-2">{{ __('Hesap oluşturun') }}</h2>
            <p class="text-secondary text-center mb-4">{{ __('E-postanızı doğruladıktan sonra hesabınız yönetici onayıyla açılır.') }}</p>

            <x-auth.google-button />

            <form method="post" action="{{ route('register.store') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="name">{{ __('Ad soyad') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" autocomplete="name" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('E-posta') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('Şifre') }}</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">{{ __('En az 8 karakter.') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">{{ __('Şifre (tekrar)') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('Kayıt ol') }}</button>
            </form>
        </div>
    </div>
    <div class="text-center text-secondary mt-3">
        {{ __('Zaten hesabınız var mı?') }} <a href="{{ route('login') }}">{{ __('Giriş yapın') }}</a>
    </div>
@endsection
