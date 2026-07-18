@extends('layout')

@section('title', $domain->domain)

@section('page-pretitle')
    <a href="{{ route('dashboard') }}" class="text-secondary">&larr; Domainler</a>
@endsection
@section('page-title')
    {{ $domain->domain }}
    @if (auth()->user()->is_admin)
        <span class="badge bg-secondary-lt ms-2">Sahibi: {{ $domain->user?->email ?? '—' }}</span>
    @endif
@endsection
@section('page-actions')
    <form method="post" action="{{ route('domains.check', $domain) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-primary">
            <i class="ti ti-refresh icon"></i> Site Kontrolü Yap
        </button>
    </form>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-keyword">
        <i class="ti ti-plus icon"></i> Anahtar Kelime Ekle
    </button>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Site Kontrolü</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                Belirli bir anahtar kelimeye değil, domain'in tamamına ait kontroller:
                AI crawler'ların robots.txt erişimi, sitemap.xml, llms.txt ve
                güvenlik header'ları.
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

                <div class="text-secondary small mb-2">Son kontrol: {{ $domainCheck->created_at->format('Y-m-d H:i:s') }}</div>
                <div class="mb-3">
                    @include('domains._check-badges', ['domainCheck' => $domainCheck])
                </div>

                @if (!empty($driftChanges))
                    <div class="alert alert-warning">
                        <div class="fw-medium mb-1">Bir önceki kontrole göre değişenler:</div>
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
                                AI crawler erişimi (robots.txt)
                            </button>
                        </h2>
                        <div id="ac-crawlers" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($aiCrawlers['found'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Crawler</th><th>Kim</th><th>Durum</th></tr></thead>
                                            <tbody>
                                            @foreach ($aiCrawlers['crawlers'] as $token => $info)
                                                <tr>
                                                    <td>{{ $token }}</td>
                                                    <td>{{ $info['label'] }}</td>
                                                    <td>
                                                        @if ($info['allowed'])
                                                            <span class="badge bg-success-lt">Açık</span>
                                                        @else
                                                            <span class="badge bg-warning-lt">Engelli</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">Kaynak: <a href="{{ $aiCrawlers['url'] }}" target="_blank" rel="noopener">{{ $aiCrawlers['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">robots.txt bulunamadı ({{ $aiCrawlers['url'] ?? '' }}) — varsayılan olarak tüm crawler'lar için açık kabul edilir.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-sitemap">
                                Sitemap
                            </button>
                        </h2>
                        <div id="ac-sitemap" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($sitemapData['found'] ?? false)
                                    @if ($sitemapData['is_valid_xml'] ?? false)
                                        <p>
                                            {{ ($sitemapData['is_sitemap_index'] ?? false) ? 'Sitemap index' : 'Sitemap' }},
                                            {{ $sitemapData['url_count'] ?? 0 }}
                                            {{ ($sitemapData['is_sitemap_index'] ?? false) ? 'alt-sitemap' : 'URL' }} içeriyor.
                                        </p>
                                    @else
                                        <p class="text-danger">{{ $sitemapData['error'] ?? 'Geçersiz XML.' }}</p>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $sitemapData['url'] }}" target="_blank" rel="noopener">{{ $sitemapData['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ $sitemapData['url'] ?? '' }} bulunamadı.</p>
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
                                    <p class="text-secondary mb-0">{{ $llmsTxt['url'] ?? '' }} bulunamadı.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-security">
                                Güvenlik header'ları
                            </button>
                        </h2>
                        <div id="ac-security" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($securityHeaders['reachable'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Header</th><th>Değer</th></tr></thead>
                                            <tbody>
                                            @foreach ($securityHeaders['headers'] ?? [] as $name => $value)
                                                <tr>
                                                    <td>{{ $name }}</td>
                                                    <td>
                                                        @if ($value)
                                                            {{ $value }}
                                                        @else
                                                            <span class="badge bg-warning-lt">yok</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>HTTP &rarr; HTTPS yönlendirme</td>
                                                <td>
                                                    @if ($securityHeaders['http_redirects_to_https'] ?? false)
                                                        <span class="badge bg-success-lt">var</span>
                                                    @else
                                                        <span class="badge bg-warning-lt">yok</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-danger mb-0">Domaine erişilemedi.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-canonical">
                                Kanonik host
                            </button>
                        </h2>
                        <div id="ac-canonical" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($canonicalHostData['redirected'] ?? false)
                                    <p class="mb-0">
                                        <code>{{ $canonicalHostData['original_host'] }}</code>,
                                        HTTP {{ $canonicalHostData['redirect_status'] }} ile
                                        <code>{{ $canonicalHostData['canonical_host'] }}</code>
                                        adresine yönleniyor. Gerçek trafik (ve CrUX verisi) bu host altında toplanır;
                                        site-geneli kontroller (ve CrUX sorgusu) bu adrese göre yapılır.
                                    </p>
                                @else
                                    <p class="text-secondary mb-0">
                                        <code>{{ $canonicalHostData['original_host'] ?? $domain->domain }}</code>
                                        kalıcı bir yönlendirme yapmıyor — bu host kanonik kabul edildi.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-crux">
                                CrUX (gerçek kullanıcı verisi)
                            </button>
                        </h2>
                        <div id="ac-crux" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if (!($cruxData['configured'] ?? false))
                                    <p class="text-secondary mb-0">
                                        CrUX API anahtarı tanımlı değil (<code>CRUX_API_KEY</code> veya <code>PSI_API_KEY</code>).
                                    </p>
                                @elseif ($cruxData['error'] ?? null)
                                    <p class="text-danger mb-1">
                                        <code>{{ $cruxData['origin'] ?? '' }}</code> sorgulanırken hata oluştu:
                                        {{ $cruxData['error'] }}
                                    </p>
                                    <p class="text-secondary mb-0">
                                        "PERMISSION_DENIED" / "API_KEY_SERVICE_BLOCKED" görüyorsanız: Google Cloud
                                        Console'da API anahtarının <em>API kısıtlamaları</em> listesine
                                        "Chrome UX Report API"yi ekleyin ve API'nin projede etkin olduğundan emin olun.
                                    </p>
                                @elseif (!($cruxData['found'] ?? false))
                                    <p class="text-secondary mb-0">
                                        <code>{{ $cruxData['origin'] ?? '' }}</code> için CrUX verisi bulunamadı
                                        (yeterli gerçek kullanıcı trafiği olmayabilir).
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Metrik</th><th>p75</th><th>Değerlendirme</th></tr></thead>
                                            <tbody>
                                            @foreach ($cruxData['metrics'] ?? [] as $metric)
                                                <tr>
                                                    <td>{{ $metric['label'] }}</td>
                                                    <td>{{ $metric['p75'] }}</td>
                                                    <td>
                                                        @if ($metric['rating'] === 'good')
                                                            <span class="badge bg-success-lt">iyi</span>
                                                        @elseif ($metric['rating'] === 'needs_improvement')
                                                            <span class="badge bg-warning-lt">geliştirilmeli</span>
                                                        @else
                                                            <span class="badge bg-danger-lt">zayıf</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        Köken: <code>{{ $cruxData['origin'] }}</code>
                                        @if ($cruxData['collection_period'] ?? null)
                                            &middot; Veri periyodu: {{ $cruxData['collection_period'] }}
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
                                        Google hesabınız bağlı değil. Search Console verisi için üstteki
                                        "Google hesabını bağla" bağlantısından tekrar giriş yapın.
                                    </p>
                                @elseif ($gscData['error'] ?? null)
                                    <p class="text-danger mb-0">Search Console sorgulanırken hata oluştu: {{ $gscData['error'] }}</p>
                                @elseif (!($gscData['verified'] ?? false))
                                    <p class="text-secondary mb-0">
                                        Bu domain, bağlı Google hesabınızın Search Console'unda doğrulanmış bir
                                        mülk (property) olarak bulunamadı.
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                <tr><td>Tıklama</td><td>{{ number_format($gscData['clicks'] ?? 0) }}</td></tr>
                                                <tr><td>Gösterim</td><td>{{ number_format($gscData['impressions'] ?? 0) }}</td></tr>
                                                <tr><td>CTR</td><td>{{ number_format((($gscData['ctr'] ?? 0) * 100), 2) }}%</td></tr>
                                                <tr><td>Ortalama pozisyon</td><td>{{ $gscData['average_position'] ? number_format($gscData['average_position'], 1) : '-' }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">
                                        Mülk: <code>{{ $gscData['site_url'] }}</code>
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
                                        GA4 entegrasyonu şu anda devre dışı — Google'ın "analytics.readonly"
                                        kapsamı için istediği kullanım açıklaması/demo video doğrulama süreci
                                        tamamlanınca etkinleştirilecek. Property ID'yi şimdiden girebilirsiniz.
                                    </p>
                                @elseif (!auth()->user()->hasGoogleOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        Google hesabınız bağlı değil. GA4 verisi için üstteki
                                        "Google hesabını bağla" bağlantısından tekrar giriş yapın.
                                    </p>
                                @elseif ($ga4Data['error'] ?? null)
                                    <p class="text-danger mb-0">GA4 sorgulanırken hata oluştu: {{ $ga4Data['error'] }}</p>
                                @elseif (empty($ga4Data['property_id']))
                                    <p class="text-secondary mb-2">GA4 Property ID girilmedi.</p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <tbody>
                                                <tr><td>Toplam oturum (28 gün)</td><td>{{ number_format($ga4Data['total_sessions'] ?? 0) }}</td></tr>
                                                <tr><td>Organik arama oturumu</td><td>{{ number_format($ga4Data['organic_sessions'] ?? 0) }}</td></tr>
                                                <tr><td>Aktif kullanıcı</td><td>{{ number_format($ga4Data['active_users'] ?? 0) }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-secondary mb-0">Property ID: <code>{{ $ga4Data['property_id'] }}</code></p>
                                @endif
                                @php
                                    $ga4Properties = session('ga4Properties', []);
                                @endphp
                                <form method="post" action="{{ route('domains.ga4-property.update', $domain) }}" class="d-flex gap-2 align-items-end mt-3">
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex-grow-1">
                                        <label class="form-label">GA4 Property ID</label>
                                        @if (!empty($ga4Properties))
                                            <select name="ga4_property_id" class="form-select">
                                                <option value="">— Seçiniz —</option>
                                                @foreach ($ga4Properties as $property)
                                                    <option value="{{ $property->propertyId }}" @selected($domain->ga4_property_id === $property->propertyId)>{{ $property->label }} ({{ $property->propertyId }})</option>
                                                @endforeach
                                            </select>
                                            <small class="form-hint">Google hesabınızda bulunan property'ler listelendi.</small>
                                        @else
                                            <input type="text" name="ga4_property_id" class="form-control" value="{{ $domain->ga4_property_id }}" placeholder="ör. 123456789">
                                            <small class="form-hint">GA4 Yönetici paneli &rarr; Mülk ayarları'ndan kopyalayın, ya da aşağıdan listeyi getirin.</small>
                                        @endif
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary">Kaydet</button>
                                </form>
                                @if (empty($ga4Properties) && auth()->user()->hasGoogleOfflineAccess())
                                    <form method="post" action="{{ route('domains.ga4-properties.fetch', $domain) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="ti ti-refresh icon"></i> Property listesini getir
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-bing">
                                Backlink (Bing Webmaster)
                            </button>
                        </h2>
                        <div id="ac-bing" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if (!auth()->user()->hasBingOfflineAccess())
                                    <p class="text-secondary mb-0">
                                        Bing hesabınız bağlı değil. Backlink verisi için üstteki
                                        "Bing hesabını bağla" bağlantısından bağlanın.
                                    </p>
                                @elseif ($bingData['error'] ?? null)
                                    <p class="text-danger mb-0">Bing Webmaster sorgulanırken hata oluştu: {{ $bingData['error'] }}</p>
                                @elseif (!($bingData['verified'] ?? false))
                                    <p class="text-secondary mb-0">
                                        Bu domain, bağlı Bing hesabınızda doğrulanmış bir site olarak bulunamadı.
                                        Not: bu, genel bir backlink indeksi değildir — yalnızca kendi Bing Webmaster
                                        hesabınızda doğruladığınız siteler için veri döner.
                                    </p>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Sayfa</th><th>Inbound link sayısı</th></tr></thead>
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
                                        Toplam {{ number_format($bingData['total_links'] ?? 0) }} inbound link,
                                        {{ number_format($bingData['pages_with_links'] ?? 0) }} sayfaya dağılmış.
                                        Mülk: <code>{{ $bingData['site_url'] }}</code>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-secondary mb-0">Henüz site kontrolü çalıştırılmadı.</p>
            @endif
        </div>
    </div>

    @if ($sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Keşfedilen Sayfalar (Sitemap)</h3>
                <div class="card-actions">
                    <span class="badge bg-success-lt">{{ $sitemapUrlCounts['active'] }} sitemap'te</span>
                    @if ($sitemapUrlCounts['removed'] > 0)
                        <span class="badge bg-warning-lt">{{ $sitemapUrlCounts['removed'] }} kaldırıldı</span>
                    @endif
                    <a href="{{ route('domains.lighthouse-report', $domain) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-gauge icon"></i> Sayfa Raporu
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                <p class="text-secondary mb-0">
                    Sitemap.xml'de bulunan sayfalar; her "Site Kontrolü Yap" çalıştırmasında yeniden okunur.
                    Bir sayfa sitemap'ten kaldırılırsa (silinmez, sadece) "kaldırıldı" olarak işaretlenir.
                </p>
            </div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table card-table table-vcenter table-sm">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>İlk görülme</th>
                            <th>Son görülme</th>
                            <th>Durum</th>
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
                                        <span class="badge bg-warning-lt">kaldırıldı: {{ $sitemapUrl->removed_at->format('Y-m-d') }}</span>
                                    @else
                                        <span class="badge bg-success-lt">sitemap'te</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($sitemapUrlCounts['active'] + $sitemapUrlCounts['removed'] > $sitemapUrls->count())
                <div class="card-body py-2">
                    <p class="text-secondary mb-0 small">İlk {{ $sitemapUrls->count() }} kayıt gösteriliyor.</p>
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Anahtar Kelimeler</h3>
        </div>
        <div class="card-body border-bottom py-2">
            <p class="text-secondary mb-0">"Kontrol Et" butonu SERP + on-page + Lighthouse taramasını aynı anda çalıştırır; bu 20-60 saniye sürebilir, sayfa kapanmadan bekleyin.</p>
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
                    Ana sayfa HTML'inden otomatik çıkarılan anahtar kelime önerileri — eklemek için "+", istemiyorsanız "×" tıklayın:
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
                                <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Öneriyi kaldır: {{ $suggestion['phrase'] }}">
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
                <p class="empty-title">Henüz anahtar kelime eklenmedi</p>
                <div class="empty-action">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-keyword">
                        <i class="ti ti-plus icon"></i> Anahtar Kelime Ekle
                    </button>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>Kelime</th>
                            <th>Sayfa</th>
                            <th>Son sıralama</th>
                            <th>AI Overview</th>
                            <th>Lighthouse (Perf/SEO/Eris/BP)</th>
                            <th>Son kontrol</th>
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
                                        <span class="badge bg-warning-lt">engellendi</span>
                                    @elseif ($last->target_position)
                                        <strong>{{ $last->target_position }}</strong>
                                    @else
                                        <span class="text-secondary">bulunamadı</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!$last)
                                        <span class="text-secondary">-</span>
                                    @elseif ($last->ai_overview_present)
                                        @if ($last->ai_overview_target_cited)
                                            <span class="badge bg-success-lt">var, kaynakta</span>
                                        @else
                                            <span class="badge bg-warning-lt">var, kaynakta değil</span>
                                        @endif
                                    @else
                                        <span class="text-secondary">yok</span>
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
                                            <button type="submit" class="btn btn-icon btn-outline-primary" aria-label="Kontrol Et">
                                                <i class="ti ti-refresh"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('keywords.destroy', $kw) }}" onsubmit="return confirm('Bu anahtar kelime silinsin mi?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-ghost-danger" aria-label="Sil">
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
                        <h5 class="modal-title">Anahtar Kelime Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Anahtar kelime</label>
                            <input type="text" name="keyword" class="form-control" required autofocus>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">On-page analiz sayfası (opsiyonel)</label>
                            <input type="url" name="url" class="form-control" placeholder="https://{{ $domain->domain }}/sayfa">
                            <small class="form-hint">Boş bırakılırsa https://{{ $domain->domain }}/ kullanılır.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-plus icon"></i> Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
