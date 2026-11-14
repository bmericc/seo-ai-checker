@extends('layout-center')

@section('title', __('Yeni şifre'))

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-4">{{ __('Yeni şifre belirleyin') }}</h2>
            <form method="post" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('E-posta') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('Yeni şifre') }}</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required autofocus>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-hint">{{ __('En az 8 karakter.') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">{{ __('Yeni şifre (tekrar)') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('Şifreyi kaydet') }}</button>
            </form>
        </div>
    </div>
@endsection
