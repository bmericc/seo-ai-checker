@extends('layout')

@section('title', 'Giris yap')

@section('content')
    <div class="login-box">
        <h1>SEO / AI Overview Checker</h1>
        <p class="muted">Bu panele yalnizca izin verilen Google hesaplariyla giris yapilabilir.</p>
        <a href="{{ route('google.redirect') }}" class="btn-google">Google ile giris yap</a>
    </div>
@endsection
