@extends('layout-center')

@section('title', __('Giriş yap'))

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-4">{{ __('Hesabınıza giriş yapın') }}</h2>

            <x-auth.google-button />

            <form method="post" action="{{ route('login.attempt') }}" autocomplete="on" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('E-posta') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label class="form-label" for="password">
                        {{ __('Şifre') }}
                        <span class="form-label-description"><a href="{{ route('password.request') }}">{{ __('Şifremi unuttum') }}</a></span>
                    </label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" name="remember" value="1" class="form-check-input" @checked(old('remember'))>
                        <span class="form-check-label">{{ __('Beni hatırla') }}</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('Giriş yap') }}</button>
            </form>
        </div>
    </div>
    <div class="text-center text-secondary mt-3">
        {{ __('Hesabınız yok mu?') }} <a href="{{ route('register') }}">{{ __('Kayıt olun') }}</a>
    </div>
@endsection
