@extends('layout')

@section('title', 'Giris hatasi')

@section('content')
    <h1>Giris yapilamadi</h1>
    <p class="text-error">{{ $message }}</p>
    <p><a href="{{ route('dashboard') }}">Tekrar dene</a></p>
@endsection
