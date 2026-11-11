@extends('layout')

@section('public-shell', '1')
@section('title', __('Erişim izni'))

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@php
    $scopeLabels = \App\Mcp\McpScopes::descriptions();
    $scopeIds = collect($scopes)->map(fn ($scope) => $scope->id)->all();
    $canWrite = in_array(\App\Mcp\McpScopes::WRITE, $scopeIds, true);
    // Istemci yazma istemediyse kullanici yine de verebilsin (Grok gibi
    // istemciler scope gondermiyor). Istemcinin scope listesi kisitliysa
    // ve write icermiyorsa secenek gosterilmez.
    $offerWrite = !$canWrite && $client->hasScope(\App\Mcp\McpScopes::WRITE);
@endphp

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar avatar-md bg-primary-lt"><i class="ti ti-plug-connected fs-2"></i></span>
                        <div style="min-width: 0">
                            <h2 class="h3 mb-0 text-truncate">{{ $client->name }}</h2>
                            <div class="text-secondary small">{{ __('hesabınıza bağlanmak istiyor') }}</div>
                        </div>
                    </div>

                    <p class="text-secondary">
                        {{ __(':app uygulamasındaki verilerinize bir yapay zekâ asistanı (MCP istemcisi) üzerinden erişim isteniyor. Yalnızca :email hesabının görebildiği domainler paylaşılır.', ['app' => config('app.name'), 'email' => $user->email]) }}
                    </p>

                    <div class="fw-semibold mb-2">{{ __('İstenen izinler') }}</div>
                    <ul class="list-unstyled d-flex flex-column gap-2 mb-3">
                        @forelse ($scopes as $scope)
                            <li class="d-flex gap-2">
                                <i class="ti {{ $scope->id === \App\Mcp\McpScopes::WRITE ? 'ti-pencil text-warning' : 'ti-eye text-success' }} mt-1"></i>
                                <span>{{ $scopeLabels[$scope->id] ?? $scope->description }}</span>
                            </li>
                        @empty
                            <li class="d-flex gap-2">
                                <i class="ti ti-eye text-success mt-1"></i>
                                <span>{{ $scopeLabels[\App\Mcp\McpScopes::READ] }}</span>
                            </li>
                        @endforelse
                    </ul>

                    @if ($canWrite)
                        <div class="alert alert-warning py-2 small mb-3">
                            {{ __('Yazma izniyle asistan sizin adınıza kontrol başlatabilir ve IndexNow gönderimi yapabilir. Silme işlemi yapamaz.') }}
                        </div>
                    @endif

                    @if ($offerWrite)
                        <label class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="grant_write" value="1" form="oauth-approve-form">
                            <span class="form-check-label">
                                <i class="ti ti-pencil text-warning"></i>
                                {{ __('Yazma izni de ver') }}
                                <span class="form-check-description">{{ $scopeLabels[\App\Mcp\McpScopes::WRITE] }}. {{ __('Silme işlemi yapamaz.') }}</span>
                            </span>
                        </label>
                    @endif

                    <div class="d-flex gap-2">
                        <form method="post" action="{{ route('passport.authorizations.deny') }}" class="flex-fill">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button type="submit" class="btn btn-outline-secondary w-100">{{ __('Reddet') }}</button>
                        </form>
                        <form method="post" action="{{ route('passport.authorizations.approve') }}" class="flex-fill" id="oauth-approve-form">
                            @csrf
                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button type="submit" class="btn btn-primary w-100">{{ __('İzin ver') }}</button>
                        </form>
                    </div>

                    <div class="text-secondary small mt-3">
                        {{ __('Bu izni istediğiniz zaman Bağlantılar sayfasından geri alabilirsiniz.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
