@php
    $blockedCrawlers = collect($domainCheck->ai_crawlers['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed']);
@endphp

@if (!($domainCheck->ai_crawlers['found'] ?? false))
    <span class="badge bg-secondary-lt">robots.txt yok — hepsi açık</span>
@elseif ($blockedCrawlers->isEmpty())
    <span class="badge bg-success-lt">AI crawler'lar: hepsi açık</span>
@else
    <span class="badge bg-warning-lt">AI crawler'lar: {{ $blockedCrawlers->count() }} engelli</span>
@endif

@if ($domainCheck->sitemap['found'] ?? false)
    @if ($domainCheck->sitemap['is_valid_xml'] ?? false)
        <span class="badge bg-success-lt">Sitemap: geçerli</span>
    @else
        <span class="badge bg-warning-lt">Sitemap: geçersiz XML</span>
    @endif
@else
    <span class="badge bg-warning-lt">Sitemap yok</span>
@endif

@if ($domainCheck->llms_txt['found'] ?? false)
    <span class="badge bg-success-lt">llms.txt var</span>
@else
    <span class="badge bg-secondary-lt">llms.txt yok</span>
@endif

@if ($domainCheck->security_headers['http_redirects_to_https'] ?? false)
    <span class="badge bg-success-lt">HTTPS zorunlu</span>
@else
    <span class="badge bg-warning-lt">HTTPS yönlendirmesi yok</span>
@endif

@if ($domainCheck->canonical_host['redirected'] ?? false)
    <span class="badge bg-azure-lt">Kanonik: {{ $domainCheck->canonical_host['canonical_host'] }}</span>
@endif

@if ($domainCheck->crux['configured'] ?? false)
    @if ($domainCheck->crux['found'] ?? false)
        @php($cruxRatings = collect($domainCheck->crux['metrics'])->pluck('rating'))
        @if ($cruxRatings->contains('poor'))
            <span class="badge bg-danger-lt">CrUX: zayıf</span>
        @elseif ($cruxRatings->contains('needs_improvement'))
            <span class="badge bg-warning-lt">CrUX: geliştirilmeli</span>
        @else
            <span class="badge bg-success-lt">CrUX: iyi</span>
        @endif
    @else
        <span class="badge bg-secondary-lt">CrUX: veri yok</span>
    @endif
@endif
