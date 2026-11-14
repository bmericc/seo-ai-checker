@extends('layout-center')

@section('title', __('Şifremi unuttum'))

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-2">{{ __('Şifremi unuttum') }}</h2>
            <p class="text-secondary text-center mb-4">{{ __('E-postanızı yazın, şifre belirleme bağlantısı gönderelim. Google ile kayıt olduysanız da bu yolla bir şifre belirleyebilirsiniz.') }}</p>
            <form method="post" action="{{ route('password.email') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('E-posta') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('Bağlantı gönder') }}</button>
            </form>
        </div>
    </div>
    <div class="text-center text-secondary mt-3">
        <a href="{{ route('login') }}">{{ __('Girişe dön') }}</a>
    </div>
@endsection
