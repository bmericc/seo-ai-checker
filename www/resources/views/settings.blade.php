@extends('layout')

@section('title', __('Ayarlar'))
@section('page-title', __('Ayarlar'))

@section('content')
    @php $current = app()->getLocale(); @endphp
    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <form method="post" action="{{ route('locale.update') }}" class="card">
                @csrf
                <div class="card-body">
                    <div class="fw-semibold mb-1">{{ __('Arayüz dili') }}</div>
                    <div class="text-secondary small mb-3">{{ __('Panel, e-postalar ve raporlar bu dilde gösterilir. İlk girişte tarayıcınızın diline göre seçilir.') }}</div>
                    <div class="form-selectgroup">
                        @foreach (['tr' => 'Türkçe', 'en' => 'English'] as $code => $label)
                            <label class="form-selectgroup-item">
                                <input type="radio" name="locale" value="{{ $code }}" class="form-selectgroup-input" @checked($current === $code)>
                                <span class="form-selectgroup-label" lang="{{ $code }}">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary">{{ __('Kaydet') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
