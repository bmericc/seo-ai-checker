@extends('layout')

@section('public-shell', '1')
@section('title', __('Rapor bulunamadı'))

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="empty">
        <div class="empty-icon"><i class="ti ti-link-off fs-1"></i></div>
        <p class="empty-title">{{ __('Bu rapor linki geçerli değil') }}</p>
        <p class="empty-subtitle text-secondary">{{ __('Link kapatılmış ya da süresi dolmuş olabilir. Raporu paylaşan kişiden yeni bir link isteyin.') }}</p>
    </div>
@endsection
