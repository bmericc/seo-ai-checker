@php
    // ai_crawlers/llms_txt/security_headers/canonical_host/crux domain
    // string'ine ait ortak DomainFact'ten gelir (bkz. Domain::fact()) - bu
    // sayede bu Domain kaydinin kendi kontrolu hic calismamis olsa bile,
    // ayni domain'i baska bir kullanicinin taze kontrol etmis olmasi
    // durumunda burada gorunur. sitemap/gsc/ga4/bing_backlinks ise
    // kullaniciya/kayda ozel oldugundan $domainCheck'ten (bu Domain
    // kaydinin kendi en son kontrolu, olmayabilir) gelir.
    $aiCrawlers = $fact?->ai_crawlers ?? [];
    $sitemapData = $domainCheck?->sitemap ?? [];
    $llmsTxt = $fact?->llms_txt ?? [];
    $securityHeaders = $fact?->security_headers ?? [];
    $canonicalHostData = $fact?->canonical_host ?? [];
    $cruxData = $fact?->crux ?? [];
    $gscData = $domainCheck?->gsc ?? [];
    $ga4Data = $domainCheck?->ga4 ?? [];
    $bingData = $domainCheck?->bing_backlinks ?? [];
    $blockedCrawlers = collect($aiCrawlers['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed']);
    $cruxRatings = collect($cruxData['metrics'] ?? [])->pluck('rating');
@endphp

@if (!($aiCrawlers['found'] ?? false))
    <span class="badge bg-secondary-lt">{{ __('robots.txt yok — hepsi açık') }}</span>
@elseif ($blockedCrawlers->isEmpty())
    <span class="badge bg-success-lt">{{ __("AI crawler'lar: hepsi açık") }}</span>
@else
    <span class="badge bg-warning-lt">{{ __("AI crawler'lar: :count engelli", ['count' => $blockedCrawlers->count()]) }}</span>
@endif

@if ($sitemapData['found'] ?? false)
    @if ($sitemapData['is_valid_xml'] ?? false)
        <span class="badge bg-success-lt">{{ __('Sitemap: geçerli') }}</span>
    @else
        <span class="badge bg-warning-lt">{{ __('Sitemap: geçersiz XML') }}</span>
    @endif
@else
    <span class="badge bg-warning-lt">{{ __('Sitemap yok') }}</span>
@endif

@if ($llmsTxt['found'] ?? false)
    <span class="badge bg-success-lt">{{ __('llms.txt var') }}</span>
@else
    <span class="badge bg-secondary-lt">{{ __('llms.txt yok') }}</span>
@endif

@if ($securityHeaders['http_redirects_to_https'] ?? false)
    <span class="badge bg-success-lt">{{ __('HTTPS zorunlu') }}</span>
@else
    <span class="badge bg-warning-lt">{{ __('HTTPS yönlendirmesi yok') }}</span>
@endif

@if ($canonicalHostData['redirected'] ?? false)
    <span class="badge bg-azure-lt">{{ __('Kanonik: :host', ['host' => $canonicalHostData['canonical_host']]) }}</span>
@endif

@if ($cruxData['configured'] ?? false)
    @if ($cruxData['found'] ?? false)
        @if ($cruxRatings->contains('poor'))
            <span class="badge bg-danger-lt">{{ __('CrUX: zayıf') }}</span>
        @elseif ($cruxRatings->contains('needs_improvement'))
            <span class="badge bg-warning-lt">{{ __('CrUX: geliştirilmeli') }}</span>
        @else
            <span class="badge bg-success-lt">{{ __('CrUX: iyi') }}</span>
        @endif
    @elseif ($cruxData['error'] ?? null)
        <span class="badge bg-danger-lt">{{ __('CrUX: hata') }}</span>
    @else
        <span class="badge bg-secondary-lt">{{ __('CrUX: veri yok') }}</span>
    @endif
@endif

@if ($gscData['configured'] ?? false)
    @if ($gscData['error'] ?? null)
        <span class="badge bg-danger-lt">{{ __('GSC: hata') }}</span>
    @elseif ($gscData['verified'] ?? false)
        <span class="badge bg-success-lt">{{ __('GSC: :count tıklama', ['count' => number_format($gscData['clicks'] ?? 0)]) }}</span>
    @else
        <span class="badge bg-secondary-lt">{{ __('GSC: doğrulanmamış') }}</span>
    @endif
@endif

@if (!empty($ga4Data['property_id']))
    @if ($ga4Data['error'] ?? null)
        <span class="badge bg-danger-lt">{{ __('GA4: hata') }}</span>
    @else
        <span class="badge bg-success-lt">{{ __('GA4: :count oturum', ['count' => number_format($ga4Data['total_sessions'] ?? 0)]) }}</span>
    @endif
@endif

@if ($bingData['configured'] ?? false)
    @if ($bingData['error'] ?? null)
        <span class="badge bg-danger-lt">{{ __('Backlink: hata') }}</span>
    @elseif ($bingData['verified'] ?? false)
        <span class="badge bg-success-lt">{{ __('Backlink: :count link', ['count' => number_format($bingData['total_links'] ?? 0)]) }}</span>
    @else
        <span class="badge bg-secondary-lt">{{ __("Backlink: Bing'de doğrulanmamış") }}</span>
    @endif
@endif
