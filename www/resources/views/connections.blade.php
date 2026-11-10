@extends('layout')

@section('title', __('Bağlantılar'))
@section('page-title', __('Bağlantılar'))
@section('page-subtitle', __('Veri kaynaklarınızı tek yerden yönetin. Bağlantılar tüm domainleriniz için geçerlidir.'))

@section('content')
    @php
        $user = auth()->user();
        $hasGoogle = $user->hasGoogleOfflineAccess();
        $hasBing = $user->hasBingOfflineAccess();
    @endphp

    <div class="row justify-content-center">
        <div class="col-12 col-xl-9 d-flex flex-column gap-3">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="avatar avatar-md bg-body-tertiary"><i class="ti ti-brand-google fs-2"></i></span>
                    <div class="flex-fill" style="min-width: 0; flex-basis: 320px">
                        <div class="fw-semibold">{{ __('Google — Search Console ve GA4') }}</div>
                        <div class="text-secondary small">{{ __('Tıklama, gösterim, sıralama ve ziyaretçi verisi.') }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                    @if ($hasGoogle)
                        <span class="badge bg-success-lt"><span class="badge badge-dot bg-success me-1"></span>{{ __('Bağlı') }}</span>
                        <a href="{{ route('google.redirect') }}" class="btn btn-outline-secondary">{{ __('Yeniden bağla') }}</a>
                    @else
                        <span class="badge bg-secondary-lt">{{ __('Bağlı değil') }}</span>
                        <a href="{{ route('google.redirect') }}" class="btn btn-dark">{{ __('Google hesabını bağla') }}</a>
                    @endif
                    </div>
                </div>
            </div>

            <div class="card {{ $hasBing ? '' : 'border-warning-subtle' }}">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="avatar avatar-md bg-body-tertiary"><i class="ti ti-brand-bing fs-2"></i></span>
                    <div class="flex-fill" style="min-width: 0; flex-basis: 320px">
                        <div class="fw-semibold">{{ __('Bing Webmaster Tools') }}</div>
                        <div class="text-secondary small">{{ __('Kendi doğruladığınız sitelerin backlink verisini görebilmek için Bing hesabınızı bağlamanız gerekiyor.') }}</div>
                        @if ($hasBing)
                            <div class="small text-warning mt-1">{{ __('Bing bağlantıyı zaman zaman sessizce düşürebiliyor. Backlink verisi gelmiyorsa yeniden bağlayın.') }}</div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                    @if ($hasBing)
                        <span class="badge bg-success-lt"><span class="badge badge-dot bg-success me-1"></span>{{ __('Bağlı') }}</span>
                        <a href="{{ route('bing.connect') }}" class="btn btn-outline-secondary">{{ __('Yeniden bağla') }}</a>
                    @else
                        <span class="badge bg-secondary-lt">{{ __('Bağlı değil') }}</span>
                        <a href="{{ route('bing.connect') }}" class="btn btn-dark">{{ __('Bing hesabını bağla') }}</a>
                    @endif
                    </div>
                </div>
            </div>

            @php
                $mcpUrl = url('/mcp');
                $mcpApps = $user->tokens()
                    ->with('client')
                    ->where('revoked', false)
                    ->latest()
                    ->get()
                    ->groupBy('client_id');
            @endphp
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-md bg-body-tertiary flex-shrink-0"><i class="ti ti-plug-connected fs-2"></i></span>
                        <div class="flex-fill" style="min-width: 0">
                            <div class="fw-semibold">{{ __('Yapay zekâ asistanları (MCP)') }}</div>
                            <div class="text-secondary small">{{ __('Claude, ChatGPT veya Cursor gibi bir asistana bu adresi "özel bağlayıcı" olarak ekleyin. Bağlanırken Google hesabınızla giriş yapıp izin vermeniz istenir; asistan yalnızca sizin domainlerinizi görür.') }}</div>
                        </div>
                    </div>
                    <div class="input-group mt-3">
                        <input type="text" class="form-control font-monospace" value="{{ $mcpUrl }}" readonly aria-label="{{ __('MCP sunucu adresi') }}">
                        <button type="button" class="btn btn-outline-secondary" data-copy-text="{{ $mcpUrl }}"><i class="ti ti-copy me-1"></i>{{ __('Kopyala') }}</button>
                    </div>

                    @if ($mcpApps->isNotEmpty())
                        <div class="fw-semibold small text-secondary text-uppercase mt-3 mb-2">{{ __('Erişim verdiğiniz uygulamalar') }}</div>
                        <div class="list-group list-group-flush border rounded-3">
                            @foreach ($mcpApps as $clientId => $tokens)
                                @php
                                    $scopes = $tokens->pluck('scopes')->flatten()->unique()->values();
                                    $canWrite = $scopes->contains(\App\Mcp\McpScopes::WRITE);
                                @endphp
                                <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                                    <div class="flex-fill" style="min-width: 0">
                                        <div class="fw-medium text-truncate">{{ $tokens->first()->client?->name ?? __('Bilinmeyen uygulama') }}</div>
                                        <div class="text-secondary small">
                                            {{ $canWrite ? __('Okuma ve yazma') : __('Yalnızca okuma') }}
                                            · {{ __('Son yetkilendirme: :date', ['date' => $tokens->first()->created_at?->diffForHumans()]) }}
                                        </div>
                                    </div>
                                    <form method="post" action="{{ route('connections.mcp.destroy', $clientId) }}" onsubmit="return confirm(@js(__('Bu uygulamanın erişimi kaldırılsın mı?')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Erişimi kaldır') }}</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($user->is_admin)
                <div class="card">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <span class="avatar avatar-md bg-body-tertiary"><i class="ti ti-sparkles fs-2"></i></span>
                        <div class="flex-fill" style="min-width: 0; flex-basis: 320px">
                            <div class="fw-semibold">{{ __('Yapay zekâ görünürlük kontrolü') }}</div>
                            <div class="text-secondary small">{{ __('ChatGPT, Claude ve Gemini API anahtarları her domain için ayrı girilir: domain sayfasında "Yapay zekâ görünürlüğü" sekmesi.') }}</div>
                        </div>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">{{ __('Domainlere git') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
