@extends('layout')

@section('title', __('Domainler'))

@section('page-title', __('Domainleriniz'))
@section('page-subtitle', __('Sitelerinizin SEO ve yapay zekâ görünürlüğünü tek bakışta görün.'))
@section('page-actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-domain">
        <i class="ti ti-plus icon"></i> {{ __('Domain Ekle') }}
    </button>
@endsection

@section('content')
    @php
        $user = auth()->user();
        $hasGoogle = $user->hasGoogleOfflineAccess();
        $hasBing = $user->hasBingOfflineAccess();
        $setupDone = (int) $domains->isNotEmpty() + (int) $hasGoogle + (int) $hasBing;

        $healthByDomain = $domains->mapWithKeys(fn ($d) => [$d->id => \App\Support\DomainHealth::for($d)]);
        $issueTotal = $healthByDomain->sum(fn ($h) => count($h->issues));
        $keywordTotal = $domains->sum('keywords_count');
        $needsAttention = $healthByDomain->filter(fn ($h) => count($h->issues) > 0)->count();
    @endphp

    {{-- Kurulum listesi: eski ust uste uyari bantlarinin yerine. --}}
    @if ($setupDone < 3)
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="card-title mb-1">{{ __('Kurulumu tamamlayın') }}</h3>
                        <div class="text-secondary">{{ __('Hesaplarınızı bağladığınızda raporlarınız gerçek trafik ve backlink verisiyle dolar.') }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="min-width: 200px">
                        <div class="meter flex-fill"><div class="meter-bar bg-success" style="--value: {{ round($setupDone / 3 * 100) }}%"></div></div>
                        <span class="fw-bold">{{ $setupDone }} / 3</span>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <div class="setup-step {{ $domains->isNotEmpty() ? 'is-done' : '' }}">
                            <span class="setup-step-icon">@if ($domains->isNotEmpty())<i class="ti ti-check"></i>@else 1 @endif</span>
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <div class="fw-semibold">{{ __('İlk domaininizi ekleyin') }}</div>
                                    <div class="small text-secondary">{{ __('Adresi yazmanız yeterli, kontrolleri biz yaparız.') }}</div>
                                </div>
                                @if ($domains->isEmpty())
                                    <button type="button" class="btn btn-sm btn-dark align-self-start" data-bs-toggle="modal" data-bs-target="#modal-add-domain">{{ __('Domain Ekle') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="setup-step {{ $hasGoogle ? 'is-done' : '' }}">
                            <span class="setup-step-icon">@if ($hasGoogle)<i class="ti ti-check"></i>@else 2 @endif</span>
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $hasGoogle ? __('Google hesabı bağlı') : __('Google hesabını bağlayın') }}</div>
                                    <div class="small text-secondary">
                                        {{ $hasGoogle
                                            ? __('Search Console ve GA4 verileri raporlara ekleniyor.')
                                            : __('Search Console ve GA4 verilerini görebilmek için Google hesabınızı yeniden bağlamanız gerekiyor.') }}
                                    </div>
                                </div>
                                @unless ($hasGoogle)
                                    <a href="{{ route('google.redirect') }}" class="btn btn-sm btn-dark align-self-start">{{ __('Google hesabını bağla') }}</a>
                                @endunless
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="setup-step {{ $hasBing ? 'is-done' : '' }}">
                            <span class="setup-step-icon">@if ($hasBing)<i class="ti ti-check"></i>@else 3 @endif</span>
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $hasBing ? __('Bing hesabınız bağlı.') : __("Bing Webmaster'ı bağlayın") }}</div>
                                    <div class="small text-secondary">
                                        @if ($hasBing)
                                            <a href="{{ route('bing.connect') }}">{{ __('Sorun yaşıyorsanız yeniden bağlayın') }}</a>
                                        @else
                                            {{ __('Kendi doğruladığınız sitelerin backlink verisini görebilmek için Bing hesabınızı bağlamanız gerekiyor.') }}
                                        @endif
                                    </div>
                                </div>
                                @unless ($hasBing)
                                    <a href="{{ route('bing.connect') }}" class="btn btn-sm btn-dark align-self-start">{{ __('Bing hesabını bağla') }}</a>
                                @endunless
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{--
            Bing'in refresh token akisi kendi tarafinda arizali (bkz.
            BingTokenService) - "bagli" gorunse bile token sessizce
            gecersizlesebiliyor, bu yuzden yeniden baglanma linki hep
            erisilebilir kalir.
        --}}
        <p class="text-secondary small mb-3">
            {{ __('Bing hesabınız bağlı.') }}
            <a href="{{ route('bing.connect') }}">{{ __('Sorun yaşıyorsanız yeniden bağlayın') }}</a>
        </p>
    @endif

    @if ($domains->isEmpty())
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-world fs-1"></i></div>
                <p class="empty-title">{{ __('Henüz domain eklenmedi') }}</p>
                <p class="empty-subtitle text-secondary">{{ __('Takibe başlamak için bir domain ekleyin.') }}</p>
                <div class="empty-action">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-domain">
                        <i class="ti ti-plus icon"></i> {{ __('Domain Ekle') }}
                    </button>
                </div>
            </div>
        </div>
    @else
        {{-- Ozet --}}
        <div class="row row-cards mb-4">
            <div class="col-6 col-lg-3">
                <div class="card card-sm"><div class="card-body">
                    <div class="text-secondary small fw-medium">{{ __('Takip edilen domain') }}</div>
                    <div class="h1 mb-0 mt-1" data-count-to="{{ $domains->count() }}">0</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm"><div class="card-body">
                    <div class="text-secondary small fw-medium">{{ __('Dikkat isteyen konu') }}</div>
                    <div class="h1 mb-0 mt-1 {{ $issueTotal > 0 ? 'text-warning' : 'text-success' }}" data-count-to="{{ $issueTotal }}">0</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm"><div class="card-body">
                    <div class="text-secondary small fw-medium">{{ __('Anahtar kelime') }}</div>
                    <div class="h1 mb-0 mt-1" data-count-to="{{ $keywordTotal }}">0</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-sm"><div class="card-body">
                    <div class="text-secondary small fw-medium">{{ __('Sorunsuz domain') }}</div>
                    <div class="h1 mb-0 mt-1 text-success" data-count-to="{{ $healthByDomain->filter(fn ($h) => $h->hasData && count($h->issues) === 0)->count() }}">0</div>
                </div></div>
            </div>
        </div>

        {{-- Arama + filtre --}}
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="input-icon flex-fill" style="max-width: 420px; min-width: 220px">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <label for="domain-search" class="visually-hidden">{{ __('Domain ara') }}</label>
                <input id="domain-search" type="search" class="form-control" placeholder="{{ __('Domain ara…') }}" data-domain-search>
            </div>
            <div class="btn-group" role="group" aria-label="{{ __('Filtre') }}">
                <button type="button" class="btn active" data-domain-filter="all" aria-pressed="true">{{ __('Tümü') }}</button>
                <button type="button" class="btn" data-domain-filter="attention" aria-pressed="false">{{ __('Dikkat isteyenler') }} <span class="badge bg-warning-lt ms-1">{{ $needsAttention }}</span></button>
                <button type="button" class="btn" data-domain-filter="ok" aria-pressed="false">{{ __('Sorunsuz') }}</button>
            </div>
        </div>

        <div class="domain-grid" data-domain-grid>
            @foreach ($domains as $domain)
                @php
                    $access = $domain->accessLevelFor($user);
                    $isShared = $domain->user_id !== $user->id && !$user->is_admin;
                    $myMembership = $isShared ? $domain->membershipFor($user) : null;
                    $health = $healthByDomain[$domain->id];
                    $status = !$health->hasData ? 'pending' : (count($health->issues) > 0 ? 'attention' : 'ok');
                    $toneText = ['success' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger', 'secondary' => 'text-secondary'][$health->tone()];
                    $lastChecked = $domain->latestDomainCheck?->created_at ?? $domain->fact?->checked_at;
                    // Kartta en fazla 3 satir: once sorunlar, kalan yere sorunsuz kontroller.
                    $lines = collect($health->issues)->map(fn ($i) => ['icon' => $i['severity'] === 'high' ? 'ti-circle-x text-danger' : 'ti-alert-circle text-warning', 'text' => $i['title']])
                        ->concat(collect($health->checks)->where('status', 'ok')->map(fn ($c) => ['icon' => 'ti-circle-check text-success', 'text' => $c['label'] . ': ' . $c['detail']]))
                        ->take(3);
                @endphp
                <div class="card domain-card {{ $status === 'attention' && $health->tone() === 'danger' ? 'border-danger-subtle' : '' }}"
                     data-domain-card data-domain-name="{{ mb_strtolower($domain->domain) }}" data-domain-status="{{ $status }}">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <x-score-ring :score="$health->score" :tone="$health->tone()" />
                            <div class="flex-fill" style="min-width: 0">
                                <a href="{{ route('domains.show', $domain) }}" class="h3 mb-1 d-block text-truncate stretched-title">{{ $domain->domain }}</a>
                                <div class="small fw-medium {{ $toneText }}">
                                    @if ($status === 'pending')
                                        {{ __('İlk kontrol bekleniyor…') }}
                                    @else
                                        {{ $health->summary() }}
                                    @endif
                                </div>
                                @if ($user->is_admin)
                                    <div class="small text-secondary text-truncate">{{ $domain->user?->email ?? '—' }}</div>
                                @elseif ($isShared)
                                    <div class="small text-secondary text-truncate">
                                        <i class="ti ti-users me-1"></i>{{ __(':email paylaştı', ['email' => $domain->user?->email ?? '—']) }}
                                        · {{ $access === \App\Models\DomainMember::WRITE ? __('Düzenleyebilir') : __('Salt okunur') }}
                                    </div>
                                @endif
                            </div>
                            <div class="dropdown">
                                <button type="button" class="btn btn-ghost-secondary btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="{{ __(':domain için işlemler', ['domain' => $domain->domain]) }}">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if ($access !== \App\Models\DomainMember::READ)
                                        <form method="post" action="{{ route('domains.check', $domain) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item"><i class="ti ti-refresh icon me-1"></i>{{ __('Yeniden kontrol et') }}</button>
                                        </form>
                                        <div class="dropdown-divider"></div>
                                    @endif
                                    @if ($myMembership)
                                        <form method="post" action="{{ route('domains.members.destroy', [$domain, $myMembership]) }}" onsubmit="return confirm({{ json_encode(__(':domain paylaşımından ayrılmak istiyor musunuz?', ['domain' => $domain->domain])) }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger"><i class="ti ti-logout icon me-1"></i>{{ __('Paylaşımdan ayrıl') }}</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('domains.destroy', $domain) }}" onsubmit="return confirm('{{ __(':domain silinsin mi? Tüm anahtar kelimeler ve geçmiş de silinecek.', ['domain' => $domain->domain]) }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger" aria-label="{{ __('Sil') }}"><i class="ti ti-trash icon me-1"></i>{{ __('Sil') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($status === 'pending')
                            <p class="text-secondary small mb-0">{{ __('Robots.txt, site haritası, güvenlik ve hız kontrolleri birkaç dakika içinde tamamlanır. Bitince sonuçlar burada görünür.') }}</p>
                        @else
                            <ul class="status-list small">
                                @foreach ($lines as $line)
                                    <li><i class="ti {{ $line['icon'] }}"></i><span>{{ $line['text'] }}</span></li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="d-flex justify-content-between small text-secondary border-top pt-2 mt-auto">
                            <span>{{ trans_choice('{0} Henüz anahtar kelime yok|{1} 1 anahtar kelime|[2,*] :count anahtar kelime', $domain->keywords_count, ['count' => $domain->keywords_count]) }}</span>
                            <span>{{ $lastChecked ? __('Son kontrol: :when', ['when' => $lastChecked->diffForHumans()]) : '' }}</span>
                        </div>
                        <a href="{{ route('domains.show', $domain) }}" class="btn {{ $status === 'attention' ? 'btn-outline-warning' : 'btn-outline-primary' }} w-100">
                            {{ $status === 'attention' ? __('Sorunları gör') : __('Detayları gör') }}
                        </a>
                    </div>
                </div>
            @endforeach

            <button type="button" class="domain-card-add" data-bs-toggle="modal" data-bs-target="#modal-add-domain">
                <span class="avatar avatar-md rounded-circle bg-primary-lt"><i class="ti ti-plus fs-2"></i></span>
                <span class="fw-semibold text-body">{{ __('Yeni domain ekle') }}</span>
                <span class="small">{{ __('Adresi yazın, gerisini biz kontrol edelim.') }}</span>
            </button>
        </div>
        <p class="text-secondary mt-3" data-domain-empty-filter hidden>{{ __('Bu filtreye uyan domain yok.') }}</p>
    @endif

    <div class="modal modal-blur fade" id="modal-add-domain" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="{{ route('domains.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Hangi siteyi takip edelim?') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="add-domain-input">{{ __('Domain') }}</label>
                            <input id="add-domain-input" type="text" name="domain" class="form-control form-control-lg" placeholder="example.com" required autofocus>
                            <div class="form-hint">{{ __('"https://" veya "www" yazmanıza gerek yok, biz düzeltiriz.') }}</div>
                        </div>
                        <div class="rounded-3 p-3 bg-body-tertiary">
                            <div class="fw-semibold small mb-2">{{ __('Eklediğinizde otomatik kontrol edeceğiz:') }}</div>
                            <div class="row g-1 small text-secondary">
                                <div class="col-6"><i class="ti ti-robot me-1"></i>{{ __('Yapay zekâ bot erişimi') }}</div>
                                <div class="col-6"><i class="ti ti-sitemap me-1"></i>{{ __('Site haritası') }}</div>
                                <div class="col-6"><i class="ti ti-lock me-1"></i>{{ __('Güvenlik ve HTTPS') }}</div>
                                <div class="col-6"><i class="ti ti-gauge me-1"></i>{{ __('Sayfa hızı') }}</div>
                                <div class="col-6"><i class="ti ti-file-text me-1"></i>llms.txt</div>
                                <div class="col-6"><i class="ti ti-calendar me-1"></i>{{ __('Domain bitiş tarihi') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('İptal') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-plus icon"></i> {{ __('Ekle') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
