@extends('layout-center')

@section('title', __('Giriş hatası'))

@section('content')
    <div class="card card-md">
        <div class="card-body text-center py-4">
            <span class="avatar avatar-xl mb-3 bg-danger-lt"><i class="ti ti-alert-triangle fs-1"></i></span>
            <h2 class="mb-2">{{ __('Giriş yapılamadı') }}</h2>
            <p class="text-danger mb-4">{{ $message }}</p>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('Tekrar dene') }}</a>
        </div>
    </div>
@endsection
