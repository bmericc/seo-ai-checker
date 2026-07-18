@php
    $aiCrawlers = $domainCheck->ai_crawlers ?? [];
    $sitemapData = $domainCheck->sitemap ?? [];
    $llmsTxt = $domainCheck->llms_txt ?? [];
    $securityHeaders = $domainCheck->security_headers ?? [];
    $canonicalHostData = $domainCheck->canonical_host ?? [];
    $cruxData = $domainCheck->crux ?? [];
    $gscData = $domainCheck->gsc ?? [];
    $ga4Data = $domainCheck->ga4 ?? [];
    $bingData = $domainCheck->bing_backlinks ?? [];
    $blockedCrawlers = collect($aiCrawlers['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed']);
    $cruxRatings = collect($cruxData['metrics'] ?? [])->pluck('rating');
@endphp

@if (!($aiCrawlers['found'] ?? false))
    <span class="badge bg-secondary-lt">robots.txt yok — hepsi açık</span>
@elseif ($blockedCrawlers->isEmpty())
    <span class="badge bg-success-lt">AI crawler'lar: hepsi açık</span>
@else
    <span class="badge bg-warning-lt">AI crawler'lar: {{ $blockedCrawlers->count() }} engelli</span>
@endif

@if ($sitemapData['found'] ?? false)
    @if ($sitemapData['is_valid_xml'] ?? false)
        <span class="badge bg-success-lt">Sitemap: geçerli</span>
    @else
        <span class="badge bg-warning-lt">Sitemap: geçersiz XML</span>
    @endif
@else
    <span class="badge bg-warning-lt">Sitemap yok</span>
@endif

@if ($llmsTxt['found'] ?? false)
    <span class="badge bg-success-lt">llms.txt var</span>
@else
    <span class="badge bg-secondary-lt">llms.txt yok</span>
@endif

@if ($securityHeaders['http_redirects_to_https'] ?? false)
    <span class="badge bg-success-lt">HTTPS zorunlu</span>
@else
    <span class="badge bg-warning-lt">HTTPS yönlendirmesi yok</span>
@endif

@if ($canonicalHostData['redirected'] ?? false)
    <span class="badge bg-azure-lt">Kanonik: {{ $canonicalHostData['canonical_host'] }}</span>
@endif

@if ($cruxData['configured'] ?? false)
    @if ($cruxData['found'] ?? false)
        @if ($cruxRatings->contains('poor'))
            <span class="badge bg-danger-lt">CrUX: zayıf</span>
        @elseif ($cruxRatings->contains('needs_improvement'))
            <span class="badge bg-warning-lt">CrUX: geliştirilmeli</span>
        @else
            <span class="badge bg-success-lt">CrUX: iyi</span>
        @endif
    @elseif ($cruxData['error'] ?? null)
        <span class="badge bg-danger-lt">CrUX: hata</span>
    @else
        <span class="badge bg-secondary-lt">CrUX: veri yok</span>
    @endif
@endif

@if ($gscData['configured'] ?? false)
    @if ($gscData['error'] ?? null)
        <span class="badge bg-danger-lt">GSC: hata</span>
    @elseif ($gscData['verified'] ?? false)
        <span class="badge bg-success-lt">GSC: {{ number_format($gscData['clicks'] ?? 0) }} tıklama</span>
    @else
        <span class="badge bg-secondary-lt">GSC: doğrulanmamış</span>
    @endif
@endif

@if (!($ga4Data['disabled'] ?? false) && !empty($ga4Data['property_id']))
    @if ($ga4Data['error'] ?? null)
        <span class="badge bg-danger-lt">GA4: hata</span>
    @else
        <span class="badge bg-success-lt">GA4: {{ number_format($ga4Data['total_sessions'] ?? 0) }} oturum</span>
    @endif
@endif

@if ($bingData['configured'] ?? false)
    @if ($bingData['error'] ?? null)
        <span class="badge bg-danger-lt">Backlink: hata</span>
    @elseif ($bingData['verified'] ?? false)
        <span class="badge bg-success-lt">Backlink: {{ number_format($bingData['total_links'] ?? 0) }} link</span>
    @else
        <span class="badge bg-secondary-lt">Backlink: Bing'de doğrulanmamış</span>
    @endif
@endif
