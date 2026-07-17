@extends('layout-center')

@section('title', 'Onay bekleniyor')

@section('content')
    <div class="card card-md">
        <div class="card-body text-center py-4">
            <span class="avatar avatar-xl mb-3 bg-yellow-lt"><i class="ti ti-clock-hour-4 fs-1"></i></span>
            <h2 class="mb-2">Hesabınız onay bekliyor</h2>
            <p class="text-secondary mb-4">
                {{ auth()->user()->email }} ile giriş yaptınız ancak hesabınız
                henüz bir admin tarafından onaylanmadı. Onaylandığında panele
                erişebileceksiniz.
            </p>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="ti ti-logout icon me-1"></i>Çıkış yap
                </button>
            </form>
        </div>
    </div>
@endsection
