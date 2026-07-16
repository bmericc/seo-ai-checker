@extends('layout')

@section('title', 'Onay bekleniyor')

@section('content')
    <div class="login-box">
        <h1>Hesabınız onay bekliyor</h1>
        <p class="muted">
            {{ auth()->user()->email }} ile giriş yaptınız ancak hesabınız
            henüz bir admin tarafından onaylanmadı. Onaylandığında panele
            erişebileceksiniz.
        </p>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link-button">Çıkış yap</button>
        </form>
    </div>
@endsection
