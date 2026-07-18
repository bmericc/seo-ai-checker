@extends('layout')

@section('title', __('Kullanım Koşulları'))
@section('page-title', __('Kullanım Koşulları'))

@section('content')
    <div class="card">
    <div class="card-body legal">
        @include('legal.terms-of-service-' . app()->getLocale())
    </div>
    </div>
@endsection
