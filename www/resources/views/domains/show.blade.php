@extends('layout')

@section('title', $domain->domain)

@section('page-pretitle')
    <a href="{{ route('dashboard') }}" class="text-secondary">&larr; {{ __('Domainler') }}</a>
@endsection
@section('page-title')
    {{ $domain->domain }}
    @if (auth()->user()->is_admin)
        <span class="badge bg-secondary-lt ms-2">{{ __('Sahibi: :email', ['email' => $domain->user?->email ?? '—']) }}</span>
    @endif
@endsection
@section('page-actions')
    <form method="post" action="{{ route('domains.check', $domain) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-primary">
            <i class="ti ti-refresh icon"></i> {{ __('Site Kontrolü Yap') }}
        </button>
    </form>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-keyword">
        <i class="ti ti-plus icon"></i> {{ __('Anahtar Kelime Ekle') }}
    </button>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">WHOIS</h3>
            <div class="card-actions">
                <form method="post" action="{{ route('domains.whois.refresh', $domain) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-refresh icon"></i> {{ __('WHOIS Bilgisini Yenile') }}
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            @if (!$domain->whois_checked_at)
                <p class="text-secondary mb-0">{{ __('WHOIS bilgisi henüz kuyruğa alınmadı ya da worker tarafından işlenmedi.') }}</p>
            @elseif ($domain->whois_error)
                <p class="text-danger mb-0">{{ __('WHOIS sorgusu başarısız: :error', ['error' => $domain->whois_error]) }}</p>
            @else
                <div class="row">
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small mb-1">{{ __('Domain Yaşı') }}</div>
                        <div class="h3 mb-0">
                            @if ($domain->whoisAgeInYears() !== null)
                                {{ __(':years yıl', ['years' => $domain->whoisAgeInYears()]) }}
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </div>
                        @if ($domain->whois_registered_at)
                            <div class="text-secondary small">{{ __('Kayıt tarihi:') }} {{ $domain->whois_registered_at->format('Y-m-d') }}</div>
                        @endif
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small mb-1">{{ __('Registrar') }}</div>
                        <div class="h3 mb-0">{{ $domain->whois_registrar ?: '—' }}</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="text-secondary small mb-1">{{ __('Kayıt Süresi Sonu') }}</div>
                        <div class="h3 mb-0">{{ $domain->whois_expires_at?->format('Y-m-d') ?: '—' }}</div>
                    </div>
                </div>
                <div class="text-secondary small mt-3">{{ __('Son WHOIS kontrolü: :date', ['date' => $domain->whois_checked_at->format('Y-m-d H:i:s')]) }}</div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Site Kontrolü') }}</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                {{ __("Belirli bir anahtar kelimeye değil, domain'in tamamına ait kontroller: AI crawler'ların robots.txt erişimi, sitemap.xml, llms.txt ve güvenlik header'ları.") }}
            </p>

            @if ($domain->latestDomainCheck)
                @php
                    $domainCheck = $domain->latestDomainCheck;
                    $aiCrawlers = $domainCheck->ai_crawlers ?? [];
                    $sitemapData = $domainCheck->sitemap ?? [];
                    $llmsTxt = $domainCheck->llms_txt ?? [];
                    $securityHeaders = $domainCheck->security_headers ?? [];
                    $canonicalHostData = $domainCheck->canonical_host ?? [];
                    $cruxData = $domainCheck->crux ?? [];
                    $gscData = $domainCheck->gsc ?? [];
                    $ga4Data = $domainCheck->ga4 ?? [];
                    $bingData = $domainCheck->bing_backlinks ?? [];
                @endphp

                <div class="text-secondary small mb-2">{{ __('Son kontrol: :date', ['date' => $domainCheck->created_at->format('Y-m-d H:i:s')]) }}</div>
                <div class="mb-3">
                    @include('domains._check-badges', ['domainCheck' => $domainCheck])
                </div>

                @if (!empty($driftChanges))
                    <div class="alert alert-warning">
                        <div class="fw-medium mb-1">{{ __('Bir önceki kontrole göre değişenler:') }}</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($driftChanges as $change)
                                <li>{{ $change }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="accordion" id="site-check-accordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-crawlers">
                                {{ __("AI crawler erişimi (robots.txt)") }}
                            </button>
                        </h2>
                        <div id="ac-crawlers" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($aiCrawlers['found'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Crawler</th><th>{{ __('Kim') }}</th><th>{{ __('Durum') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($aiCrawlers['crawlers'] as $token => $info)
                                                <tr>
                                                    <td>{{ $token }}</td>
                                                    <td>{{ $info['label'] }}</td>
                                                    <td>
                                                        @if ($info['allowed'])
                                                            <span class="badge bg-success-lt">{{ __('Açık') }}</span>
                                                        @else
                                                            <span class="badge bg-warning-lt">{{ __('Engelli') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">{{ __('Kaynak:') }} <a href="{{ $aiCrawlers['url'] }}" target="_blank" rel="noopener">{{ $aiCrawlers['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ __(':url bulunamadı — varsayılan olarak tüm crawler\'lar için açık kabul edilir.', ['url' => $aiCrawlers['url'] ?? '']) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-sitemap">
                                {{ __('Sitemap') }}
                            </button>
                        </h2>
                        <div id="ac-sitemap" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($sitemapData['found'] ?? false)
                                    @if ($sitemapData['is_valid_xml'] ?? false)
                                        <p>
                                            {{ ($sitemapData['is_sitemap_index'] ?? false) ? __('Sitemap index') : __('Sitemap') }},
                                            {{ $sitemapData['url_count'] ?? 0 }}
                                            {{ ($sitemapData['is_sitemap_index'] ?? false) ? __('alt-sitemap') : 'URL' }} {{ __('içeriyor.') }}
                                        </p>
                                    @else
                                        <p class="text-danger">{{ $sitemapData['error'] ?? __('Geçersiz XML.') }}</p>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $sitemapData['url'] }}" target="_blank" rel="noopener">{{ $sitemapData['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ __(':url bulunamadı.', ['url' => $sitemapData['url'] ?? '']) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-llms">
                                llms.txt
                            </button>
                        </h2>
                        <div id="ac-llms" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($llmsTxt['found'] ?? false)
                                    @if ($llmsTxt['preview'] ?? null)
                                        <pre class="bg-body-secondary p-2 rounded">{{ $llmsTxt['preview'] }}</pre>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $llmsTxt['url'] }}" target="_blank" rel="noopener">{{ $llmsTxt['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ __(':url bulunamadı.', ['url' => $llmsTxt['url'] ?? '']) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-security">
                                {{ __("Güvenlik header'ları") }}
                            </button>
                        </h2>
                        <div id="ac-security" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($securityHeaders['reachable'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Header</th><th>{{ __('Değer') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($securityHeaders['headers'] ?? [] as $name => $value)
                                                <tr>
                                                    <td>{{ $name }}</td>
                                                    <td>
                                                        @if ($value)
                                                            {{ $value }}
                                                        @else
                                                            <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>{{ __('HTTP &rarr; HTTPS yönlendirme') }}</td>
                                                <td>
                                                    @if ($securityHeaders['http_redirects_to_https'] ?? false)
                                                        <span class="badge bg-success-lt">{{ __('var') }}</span>
                                                    @else
                                                        <span class="badge bg-warning-lt">{{ __('yok') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-danger mb-0">{{ __('Domaine erişilemedi.') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-canonical">
                                {{ __('Kanonik host') }}
                            </button>
                        </h2>
                        <div id="ac-canonical" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($canonicalHostData['redirected'] ?? false)
                                    <p class="mb-0">
                                        {!! __('<code>:originalHost</code>, HTTP :status ile <code>:canonicalHost</code> adresine yönleniyor. Gerçek trafik (ve CrUX verisi) bu host altında toplanır; site-geneli kontroller (ve CrUX sorgusu) bu adrese göre yapılır.', ['originalHost' => $canonicalHostData['original_host'], 'status' => $canonicalHostData['redirect_status'], 'canonicalHost' => $canonicalHostData['canonical_host']]) !!}
                                    </p>
                                @else
                                    <p class="text-secondary mb-0">
                                        {!! __('<code>:host</code> kalıcı bir yönlendirme yapmıyor — bu host kanonik kabul edildi.', ['host' => $canonicalHostData['original_host'] ?? $domain->domain]) !!}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-crux">
                                {{ __('CrUX (gerçek kullanıcı verisi)') }}
                            </button>
                        </h2>
                        <div id="ac-crux" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @php
                                    $cruxMetricDescriptions = [
                                        'LCP' => __('Largest Contentful Paint — sayfadaki en büyük içerik öğesinin (görsel, video, blok metin) görünür hale gelme süresi. Yükleme hızını gösterir.'),
                                        'INP' => __('Interaction to Next Paint — kullanıcının tıklama/dokunma/tuş basma gibi etkileşimlerine sayfanın tepki verme süresi. Etkileşim duyarlılığını gösterir.'),
                                        'CLS' => __('Cumulative Layout Shift — sayfa yüklenirken öğelerin beklenmedik şekilde yer değiştirmesinin toplam skoru. Görsel kararlılığı gösterir.'),
                                        'FCP' => __('First Contentful Paint — tarayıcının ilk metin/görsel içeriği ekrana çizdiği an. Sayfanın "boş değil" hissini verdiği süredir.'),
                                        'TTFB' => __('Time to First Byte — tarayıcının sunucudan yanıtın ilk baytını alma süresi. Sunucu/ağ gecikmesini gösterir.'),
                                    ];
                                @endphp
                                @if (!($cruxData['configured'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {!! __('CrUX API anahtarı tanımlı değil (<code>CRUX_API_KEY</code> veya <code>PSI_API_KEY</code>).') !!}
                                    </p>
                                @elseif ($cruxData['error'] ?? null)
                                    <p class="text-danger mb-1">
                                        {!! __('<code>:origin</code> sorgulanırken hata oluştu: :error', ['origin' => $cruxData['origin'] ?? '', 'error' => $cruxData['error']]) !!}
                                    </p>
                                    <p class="text-secondary mb-0">
                                        {{ __('"PERMISSION_DENIED" / "API_KEY_SERVICE_BLOCKED" görüyorsanız: Google Cloud Console\'da API anahtarının API kısıtlamaları listesine "Chrome UX Report API"yi ekleyin ve API\'nin projede etkin olduğundan emin olun.') }}
                                    </p>
                                @elseif (!($cruxData['found'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {!! __('<code>:origin</code> için CrUX verisi bulunamadı (yeterli gerçek kullanıcı trafiği olmayabilir).', ['origin' => $cruxData['origin'] ?? '']) !!}
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>{{ __('Metrik') }}</th><th>p75</th><th>{{ __('Değerlendirme') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($cruxData['metrics'] ?? [] as $metric)
                                                <tr>
                                                    <td>
                                                        <span
                                                            @if ($cruxMetricDescriptions[$metric['label']] ?? null)
                                                                data-bs-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                title="{{ $cruxMetricDescriptions[$metric['label']] }}"
                                                                style="cursor: help; border-bottom: 1px dotted currentColor;"
                                                            @endif
                                                        >{{ $metric['label'] }}</span>
                                                    </td>
                                                    <td>{{ $metric['p75'] }}</td>
                                                    <td>
                                                        @if ($metric['rating'] === 'good')
                                                            <span class="badge bg-success-lt">{{ __('iyi') }}</span>
                                                        @elseif ($metric['rating'] === 'needs_improvement')
                                                            <span class="badge bg-warning-lt">{{ __('geliştirilmeli') }}</span>
                                                        @else
                                                            <span class="badge bg-danger-lt">{{ __('zayıf') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        {{ __('Köken:') }} <code>{{ $cruxData['origin'] }}</code>
                                        @if ($cruxData['collection_period'] ?? null)
                                            &middot; {{ __('Veri periyodu:') }} {{ $cruxData['collection_period'] }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-gsc">
                                Search Console
                            </button>
                        </h2>
                        <div id="ac-gsc" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if (!auth()->user()->hasGoogleOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        {!! __('Google hesabınız bağlı değil. Search Console verisi için üstteki ":link" bağlantısından tekrar giriş yapın.', ['link' => __('Google hesabını bağla')]) !!}
                                    </p>
                                @elseif ($gscData['error'] ?? null)
                                    <p class="text-danger mb-0">{{ __('Search Console sorgulanırken hata oluştu: :error', ['error' => $gscData['error']]) }}</p>
                                @elseif (!($gscData['verified'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {{ __("Bu domain, bağlı Google hesabınızın Search Console'unda doğrulanmış bir mülk (property) olarak bulunamadı.") }}
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                <tr><td>{{ __('Tıklama') }}</td><td>{{ number_format($gscData['clicks'] ?? 0) }}</td></tr>
                                                <tr><td>{{ __('Gösterim') }}</td><td>{{ number_format($gscData['impressions'] ?? 0) }}</td></tr>
                                                <tr><td>CTR</td><td>{{ number_format((($gscData['ctr'] ?? 0) * 100), 2) }}%</td></tr>
                                                <tr><td>{{ __('Ortalama pozisyon') }}</td><td>{{ $gscData['average_position'] ? number_format($gscData['average_position'], 1) : '-' }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        {{ __('Mülk:') }} <code>{{ $gscData['site_url'] }}</code>
                                        @if (($gscData['period_start'] ?? null) && ($gscData['period_end'] ?? null))
                                            &middot; {{ $gscData['period_start'] }} — {{ $gscData['period_end'] }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-ga4">
                                Google Analytics 4
                            </button>
                        </h2>
                        <div id="ac-ga4" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($ga4Data['disabled'] ?? false)
                                    <p class="text-secondary mb-2">
                                        {{ __('GA4 entegrasyonu şu anda devre dışı — Google\'ın "analytics.readonly" kapsamı için istediği kullanım açıklaması/demo video doğrulama süreci tamamlanınca etkinleştirilecek. Property ID\'yi şimdiden girebilirsiniz.') }}
                                    </p>
                                @elseif (!auth()->user()->hasGoogleOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        {!! __('Google hesabınız bağlı değil. GA4 verisi için üstteki ":link" bağlantısından tekrar giriş yapın.', ['link' => __('Google hesabını bağla')]) !!}
                                    </p>
                                @elseif ($ga4Data['error'] ?? null)
                                    <p class="text-danger mb-0">{{ __('GA4 sorgulanırken hata oluştu: :error', ['error' => $ga4Data['error']]) }}</p>
                                @elseif (empty($ga4Data['property_id']))
                                    <p class="text-secondary mb-2">{{ __('GA4 Property ID girilmedi.') }}</p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                <tr><td>{{ __('Toplam oturum (28 gün)') }}</td><td>{{ number_format($ga4Data['total_sessions'] ?? 0) }}</td></tr>
                                                <tr><td>{{ __('Organik arama oturumu') }}</td><td>{{ number_format($ga4Data['organic_sessions'] ?? 0) }}</td></tr>
                                                <tr><td>{{ __('Aktif kullanıcı') }}</td><td>{{ number_format($ga4Data['active_users'] ?? 0) }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">{{ __('Property ID:') }} <code>{{ $ga4Data['property_id'] }}</code></p>
                                @endif
                                @php
                                    $ga4Properties = session('ga4Properties', []);
                                    $ga4PropertiesByAccount = collect($ga4Properties)->groupBy('accountName');
                                    $selectedGa4Property = collect($ga4Properties)->firstWhere('propertyId', $domain->ga4_property_id);
                                    $selectedGa4Account = $selectedGa4Property['accountName'] ?? null;
                                @endphp
                                <form method="post" action="{{ route('domains.ga4-property.update', $domain) }}" class="d-flex gap-2 align-items-end mt-3">
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex-grow-1">
                                        @if (!empty($ga4Properties))
                                            <label class="form-label">{{ __('GA4 Hesabı') }}</label>
                                            <select class="form-select mb-2" data-ga4-account-select="{{ $domain->id }}">
                                                <option value="">{{ __('— Hesap seçiniz —') }}</option>
                                                @foreach ($ga4PropertiesByAccount as $accountName => $accountProperties)
                                                    <option value="{{ $accountName }}" @selected($selectedGa4Account === $accountName)>{{ $accountName }} ({{ $accountProperties->count() }})</option>
                                                @endforeach
                                            </select>
                                            <label class="form-label">GA4 Property</label>
                                            <select name="ga4_property_id" class="form-select" data-ga4-property-select="{{ $domain->id }}" @if(!$selectedGa4Account) disabled @endif>
                                                <option value="">{{ $selectedGa4Account ? __('— Seçiniz —') : __('— Önce hesap seçin —') }}</option>
                                                @foreach ($ga4Properties as $property)
                                                    <option
                                                        value="{{ $property['propertyId'] }}"
                                                        data-account="{{ $property['accountName'] }}"
                                                        @selected($domain->ga4_property_id === $property['propertyId'])
                                                        @if($selectedGa4Account !== $property['accountName']) hidden @endif
                                                    >{{ $property['label'] }} ({{ $property['propertyId'] }})</option>
                                                @endforeach
                                            </select>
                                            <small class="form-hint">{{ __('Önce hesabınızı, ardından property\'nizi seçin. :accountCount hesap ve :propertyCount property bulundu.', ['accountCount' => $ga4PropertiesByAccount->count(), 'propertyCount' => count($ga4Properties)]) }}</small>
                                        @else
                                            <label class="form-label">GA4 Property ID</label>
                                            <input type="text" name="ga4_property_id" class="form-control" value="{{ $domain->ga4_property_id }}" placeholder="{{ __('ör. 123456789') }}">
                                            <small class="form-hint">{{ __("GA4 Yönetici paneli &rarr; Mülk ayarları'ndan kopyalayın, ya da aşağıdan listeyi getirin.") }}</small>
                                        @endif
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary">{{ __('Kaydet') }}</button>
                                </form>
                                @if (empty($ga4Properties) && auth()->user()->hasGoogleOfflineAccess())
                                    <form method="post" action="{{ route('domains.ga4-properties.fetch', $domain) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="ti ti-refresh icon"></i> {{ __('Property listesini getir') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-bing">
                                {{ __('Backlink (Bing Webmaster)') }}
                            </button>
                        </h2>
                        <div id="ac-bing" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if (!auth()->user()->hasBingOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        {!! __('Bing hesabınız bağlı değil. Backlink verisi için üstteki ":link" bağlantısından bağlanın.', ['link' => __('Bing hesabını bağla')]) !!}
                                    </p>
                                @elseif ($bingData['error'] ?? null)
                                    <p class="text-danger mb-0">{{ __('Bing Webmaster sorgulanırken hata oluştu: :error', ['error' => $bingData['error']]) }}</p>
                                @elseif (!($bingData['verified'] ?? false))
                                    <p class="text-secondary mb-0">
                                        {{ __('Bu domain, bağlı Bing hesabınızda doğrulanmış bir site olarak bulunamadı. Not: bu, genel bir backlink indeksi değildir — yalnızca kendi Bing Webmaster hesabınızda doğruladığınız siteler için veri döner.') }}
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>{{ __('Sayfa') }}</th><th>{{ __('Inbound link sayısı') }}</th></tr></thead>
                                            <tbody>
                                            @foreach ($bingData['top_pages'] ?? [] as $page)
                                                <tr>
                                                    <td class="text-truncate" style="max-width: 420px;">
                                                        <a href="{{ $page['url'] }}" target="_blank" rel="noopener">{{ $page['url'] }}</a>
                                                    </td>
                                                    <td>{{ $page['count'] }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        {{ __('Toplam :total inbound link, :pages sayfaya dağılmış. Mülk: :siteUrl', ['total' => number_format($bingData['total_links'] ?? 0), 'pages' => number_format($bingData['pages_with_links'] ?? 0), 'siteUrl' => $bingData['site_url']]) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-secondary mb-0">{{ __('Henüz site kontrolü çalıştırılmadı.') }}</p>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Geçmiş Skorlar') }}</h3>
        </div>
        <div class="card-body">
            @php
                $aiSnapshot = $domain->aiVisibilitySnapshot();
            @endphp
            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <div class="text-secondary small mb-1">{{ __('AI Overview Görünürlük Skoru') }}</div>
                    @if ($aiSnapshot['score'] === null)
                        <div class="h2 mb-1 text-secondary">—</div>
                        <div class="text-secondary small">{{ __('Henüz hiçbir anahtar kelimede AI Overview gözlemlenmedi.') }}</div>
                    @else
                        <div class="h2 mb-1">{{ number_format($aiSnapshot['score'], 0) }}%</div>
                        <div class="text-secondary small">{{ __(':cited / :present anahtar kelimede AI Overview kaynağında domain gösteriliyor', ['cited' => $aiSnapshot['cited_count'], 'present' => $aiSnapshot['present_count']]) }}</div>
                    @endif
                </div>
            </div>

            @if (empty($scoreHistory['labels']))
                <p class="text-secondary mb-0">{{ __('Grafikler için tamamlanmış en az bir kontrol gerekir.') }}</p>
            @else
                <div class="row row-cards">
                    <div class="col-12 col-lg-4">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('AI Overview görünürlük trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[['label' => __('Görünürlük'), 'color' => '#0ca678', 'values' => $scoreHistory['ai_visibility']]]"
                                    :min="0"
                                    :max="100"
                                    value-suffix="%"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Lighthouse skor trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[
                                        ['label' => __('Performans'), 'color' => '#206bc4', 'values' => $scoreHistory['lighthouse_performance']],
                                        ['label' => 'SEO', 'color' => '#2fb344', 'values' => $scoreHistory['lighthouse_seo']],
                                        ['label' => __('Erişilebilirlik'), 'color' => '#ae3ec9', 'values' => $scoreHistory['lighthouse_accessibility']],
                                        ['label' => 'Best Practices', 'color' => '#f76707', 'values' => $scoreHistory['lighthouse_best_practices']],
                                    ]"
                                    :min="0"
                                    :max="100"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Ortalama SERP pozisyon trendi') }}</div>
                                <x-line-chart
                                    :labels="$scoreHistory['labels']"
                                    :series="[['label' => __('Pozisyon'), 'color' => '#4263eb', 'values' => $scoreHistory['average_position']]]"
                                    :min="1"
                                    :invert-y="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @unless (empty($domainCheckHistory['labels']))
                <hr class="my-4">
                <h4 class="mb-3">{{ __('Site Kontrolü Geçmişi') }}</h4>
                <div class="row row-cards mb-3">
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('CrUX yükleme süresi trendi (ms)') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[
                                        ['label' => 'LCP', 'color' => '#206bc4', 'values' => $domainCheckHistory['crux_lcp']],
                                        ['label' => 'FCP', 'color' => '#2fb344', 'values' => $domainCheckHistory['crux_fcp']],
                                        ['label' => 'TTFB', 'color' => '#ae3ec9', 'values' => $domainCheckHistory['crux_ttfb']],
                                        ['label' => 'INP', 'color' => '#f76707', 'values' => $domainCheckHistory['crux_inp']],
                                    ]"
                                    :min="0"
                                    value-suffix="ms"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('CrUX CLS trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => 'CLS', 'color' => '#d63939', 'values' => $domainCheckHistory['crux_cls']]]"
                                    :min="0"
                                    :decimals="3"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-cards mb-3">
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Search Console tıklama trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => __('Tıklama'), 'color' => '#206bc4', 'values' => $domainCheckHistory['gsc_clicks']]]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Search Console gösterim trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => __('Gösterim'), 'color' => '#2fb344', 'values' => $domainCheckHistory['gsc_impressions']]]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">CTR {{ __('trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => 'CTR', 'color' => '#ae3ec9', 'values' => $domainCheckHistory['gsc_ctr']]]"
                                    :min="0"
                                    value-suffix="%"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('Search Console ortalama pozisyon trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[['label' => __('Pozisyon'), 'color' => '#4263eb', 'values' => $domainCheckHistory['gsc_average_position']]]"
                                    :min="1"
                                    :invert-y="true"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-cards">
                    <div class="col-12">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="text-secondary small mb-2">{{ __('GA4 oturum ve kullanıcı trendi') }}</div>
                                <x-line-chart
                                    :labels="$domainCheckHistory['labels']"
                                    :series="[
                                        ['label' => __('Toplam oturum'), 'color' => '#206bc4', 'values' => $domainCheckHistory['ga4_total_sessions']],
                                        ['label' => __('Organik oturum'), 'color' => '#2fb344', 'values' => $domainCheckHistory['ga4_organic_sessions']],
                                        ['label' => __('Aktif kullanıcı'), 'color' => '#f76707', 'values' => $domainCheckHistory['ga4_active_users']],
                                    ]"
                                    :min="0"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            @endunless
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">{{ __('Rakip Analizi') }}</h3>
        </div>
        <div class="card-body">
            @if ($competitorAnalysis['tracked_keyword_count'] === 0)
                <p class="text-secondary mb-0">{{ __('Rakip analizi için en az bir anahtar kelimede engellenmemiş bir SERP kontrolü gerekir.') }}</p>
            @elseif (empty($competitorAnalysis['competitors']))
                <p class="text-secondary mb-0">{{ __('İzlenen anahtar kelimelerin SERP sonuçlarında tekrar eden bir rakip domain bulunamadı.') }}</p>
            @else
                <p class="text-secondary">
                    {{ __('İzlenen :count anahtar kelimenin son SERP sonuçlarında sizinle birlikte en sık çıkan domainler:', ['count' => $competitorAnalysis['tracked_keyword_count']]) }}
                </p>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>{{ __('Ortak anahtar kelime') }}</th>
                                <th>{{ __('Ortalama pozisyon') }}</th>
                                <th>{{ __('En iyi pozisyon') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($competitorAnalysis['competitors'] as $competitor)
                                <tr>
                                    <td class="fw-medium">{{ $competitor['domain'] }}</td>
                                    <td>
                                        <span
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="{{ implode(', ', $competitor['keywords']) }}"
                                            style="cursor: help; border-bottom: 1px dotted currentColor;"
                                        >{{ __(':count / :total', ['count' => $competitor['keyword_count'], 'total' => $competitorAnalysis['tracked_keyword_count']]) }}</span>
                                    </td>
                                    <td class="text-secondary">{{ $competitor['average_position'] }}</td>
                                    <td class="text-secondary">{{ $competitor['best_position'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('Keşfedilen Sayfalar (Sitemap)') }}</h3>
                <div class="card-actions">
                    <span class="badge bg-success-lt">{{ __(":count sitemap'te", ['count' => $sitemapUrlCounts['active']]) }}</span>
                    @if ($sitemapUrlCounts['removed'] > 0)
                        <span class="badge bg-warning-lt">{{ __(':count kaldırıldı', ['count' => $sitemapUrlCounts['removed']]) }}</span>
                    @endif
                    <a href="{{ route('domains.lighthouse-report', $domain) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-gauge icon"></i> {{ __('Sayfa Raporu') }}
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                <p class="text-secondary mb-0">
                    {{ __('Sitemap.xml\'de bulunan sayfalar; her "Site Kontrolü Yap" çalıştırmasında yeniden okunur. Bir sayfa sitemap\'ten kaldırılırsa (silinmez, sadece) "kaldırıldı" olarak işaretlenir.') }}
                </p>
            </div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table card-table table-vcenter table-sm">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>{{ __('İlk görülme') }}</th>
                            <th>{{ __('Son görülme') }}</th>
                            <th>{{ __('Durum') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sitemapUrls as $sitemapUrl)
                            <tr>
                                <td class="text-truncate" style="max-width: 420px;">
                                    <a href="{{ $sitemapUrl->url }}" target="_blank" rel="noopener">{{ $sitemapUrl->url }}</a>
                                </td>
                                <td class="text-secondary">{{ $sitemapUrl->first_seen_at->format('Y-m-d') }}</td>
                                <td class="text-secondary">{{ $sitemapUrl->last_seen_at->format('Y-m-d') }}</td>
                                <td>
                                    @if ($sitemapUrl->removed_at)
                                        <span class="badge bg-warning-lt">{{ __('kaldırıldı: :date', ['date' => $sitemapUrl->removed_at->format('Y-m-d')]) }}</span>
                                    @else
                                        <span class="badge bg-success-lt">{{ __("sitemap'te") }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > $sitemapUrls->count())
                <div class="card-body py-2">
                    <p class="text-secondary mb-0 small">{{ __('İlk :count kayıt gösteriliyor.', ['count' => $sitemapUrls->count()]) }}</p>
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ __('Anahtar Kelimeler') }}</h3>
        </div>
        <div class="card-body border-bottom py-2">
            <p class="text-secondary mb-0">{{ __('"Kontrol Et" butonu SERP + on-page + Lighthouse taramasını aynı anda çalıştırır; bu 20-60 saniye sürebilir, sayfa kapanmadan bekleyin.') }}</p>
        </div>

        @php
            $trackedKeywords = $domain->keywords->pluck('keyword')->map(fn ($k) => mb_strtolower(trim($k)));
            $dismissedSuggestions = collect($domain->dismissed_keyword_suggestions ?? []);
            $suggestedKeywords = collect($domain->latestDomainCheck?->suggested_keywords ?? [])
                ->reject(fn ($s) => $trackedKeywords->contains(mb_strtolower($s['phrase'])))
                ->reject(fn ($s) => $dismissedSuggestions->contains(mb_strtolower($s['phrase'])));
        @endphp
        @if ($suggestedKeywords->isNotEmpty())
            <div class="card-body border-bottom py-3">
                <div class="text-secondary small mb-2">
                    {{ __('Ana sayfa HTML\'inden otomatik çıkarılan anahtar kelime önerileri — eklemek için "+", istemiyorsanız "×" tıklayın:') }}
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($suggestedKeywords as $suggestion)
                        <div class="btn-group">
                            <form method="post" action="{{ route('keywords.store', $domain) }}">
                                @csrf
                                <input type="hidden" name="keyword" value="{{ $suggestion['phrase'] }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-plus icon"></i> {{ $suggestion['phrase'] }}
                                </button>
                            </form>
                            <form method="post" action="{{ route('domains.keyword-suggestions.dismiss', $domain) }}">
                                @csrf
                                <input type="hidden" name="phrase" value="{{ $suggestion['phrase'] }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Öneriyi kaldır: :phrase', ['phrase' => $suggestion['phrase']]) }}">
                                    <i class="ti ti-x"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($domain->keywords->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-key fs-1"></i></div>
                <p class="empty-title">{{ __('Henüz anahtar kelime eklenmedi') }}</p>
                <div class="empty-action">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-keyword">
                        <i class="ti ti-plus icon"></i> {{ __('Anahtar Kelime Ekle') }}
                    </button>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>{{ __('Kelime') }}</th>
                            <th>{{ __('Sayfa') }}</th>
                            <th>{{ __('Son sıralama') }}</th>
                            <th>AI Overview</th>
                            <th>{{ __('Lighthouse (Perf/SEO/Eris/BP)') }}</th>
                            <th>{{ __('Son kontrol') }}</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($domain->keywords as $kw)
                            @php
                                $last = $kw->latestCheck;
                            @endphp
                            <tr>
                                <td><a href="{{ route('keywords.show', $kw) }}" class="fw-medium">{{ $kw->keyword }}</a></td>
                                <td class="text-secondary">{{ $kw->url ?: $domain->rootUrl() }}</td>
                                <td>
                                    @if (!$last)
                                        <span class="text-secondary">-</span>
                                    @elseif ($last->blocked)
                                        <span class="badge bg-warning-lt">{{ __('engellendi') }}</span>
                                    @elseif ($last->target_position)
                                        <strong>{{ $last->target_position }}</strong>
                                    @else
                                        <span class="text-secondary">{{ __('bulunamadı') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!$last)
                                        <span class="text-secondary">-</span>
                                    @elseif ($last->ai_overview_present)
                                        @if ($last->ai_overview_target_cited)
                                            <span class="badge bg-success-lt">{{ __('var, kaynakta') }}</span>
                                        @else
                                            <span class="badge bg-warning-lt">{{ __('var, kaynakta değil') }}</span>
                                        @endif
                                    @else
                                        <span class="text-secondary">{{ __('yok') }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary">
                                    @if (!$last)
                                        -
                                    @else
                                        {{ $last->lighthouse_performance ?? '-' }} /
                                        {{ $last->lighthouse_seo ?? '-' }} /
                                        {{ $last->lighthouse_accessibility ?? '-' }} /
                                        {{ $last->lighthouse_best_practices ?? '-' }}
                                    @endif
                                </td>
                                <td class="text-secondary">{{ $last?->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <form method="post" action="{{ route('keywords.check', $kw) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-icon btn-outline-primary" aria-label="{{ __('Kontrol Et') }}">
                                                <i class="ti ti-refresh"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('keywords.destroy', $kw) }}" onsubmit="return confirm('{{ __('Bu anahtar kelime silinsin mi?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-ghost-danger" aria-label="{{ __('Sil') }}">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="modal modal-blur fade" id="modal-add-keyword" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="{{ route('keywords.store', $domain) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Anahtar Kelime Ekle') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Anahtar kelime') }}</label>
                            <input type="text" name="keyword" class="form-control" required autofocus>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">{{ __('On-page analiz sayfası (opsiyonel)') }}</label>
                            <input type="url" name="url" class="form-control" placeholder="https://{{ $domain->domain }}/sayfa">
                            <small class="form-hint">{{ __('Boş bırakılırsa :url kullanılır.', ['url' => 'https://' . $domain->domain . '/']) }}</small>
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
