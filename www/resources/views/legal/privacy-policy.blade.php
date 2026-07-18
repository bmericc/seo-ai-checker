@extends('layout')

@section('title', __('Gizlilik Politikası'))
@section('page-title', __('Gizlilik Politikası'))

@section('content')
    <div class="card">
    <div class="card-body legal">
        @include('legal.privacy-policy-' . app()->getLocale())
    </div>
    </div>
@endsection
