@extends('layout')

@section('title', $domain->domain)

@section('content')
    <p><a href="{{ route('dashboard') }}">&larr; Domainler</a></p>
    <h1>{{ $domain->domain }}</h1>

    <h2>Site Kontrolu</h2>
    <p class="muted">
        Belirli bir anahtar kelimeye degil, domain'in tamamina ait kontroller:
        AI crawler'larin robots.txt erisimi, sitemap.xml, llms.txt ve
        guvenlik header'lari.
    </p>
    <form method="post" action="{{ route('domains.check', $domain) }}">
        @csrf
        <button type="submit">Site Kontrolu Yap</button>
    </form>

    @if ($domain->latestDomainCheck)
        @php
            $domainCheck = $domain->latestDomainCheck;
            $blockedCrawlers = collect($domainCheck->ai_crawlers['crawlers'] ?? [])->filter(fn ($c) => !$c['allowed']);
        @endphp
        <div class="check-card">
            <div class="check-card-header">
                <strong>{{ $domainCheck->created_at->format('Y-m-d H:i:s') }}</strong>
                @if (!($domainCheck->ai_crawlers['found'] ?? false))
                    <span class="badge">robots.txt yok - hepsi acik</span>
                @elseif ($blockedCrawlers->isEmpty())
                    <span class="badge badge-ok">AI crawler'lar: hepsi acik</span>
                @else
                    <span class="badge badge-warn">AI crawler'lar: {{ $blockedCrawlers->count() }} engelli</span>
                @endif

                @if ($domainCheck->sitemap['found'] ?? false)
                    @if ($domainCheck->sitemap['is_valid_xml'] ?? false)
                        <span class="badge badge-ok">Sitemap: gecerli</span>
                    @else
                        <span class="badge badge-warn">Sitemap: gecersiz XML</span>
                    @endif
                @else
                    <span class="badge badge-warn">Sitemap yok</span>
                @endif

                @if ($domainCheck->llms_txt['found'] ?? false)
                    <span class="badge badge-ok">llms.txt var</span>
                @else
                    <span class="badge">llms.txt yok</span>
                @endif

                @if ($domainCheck->security_headers['http_redirects_to_https'] ?? false)
                    <span class="badge badge-ok">HTTPS zorunlu</span>
                @else
                    <span class="badge badge-warn">HTTPS yonlendirmesi yok</span>
                @endif
            </div>

            @if ($domainCheck->ai_crawlers['found'] ?? false)
                <details open>
                    <summary>AI crawler erisimi (robots.txt)</summary>
                    <table>
                        <thead><tr><th>Crawler</th><th>Kim</th><th>Durum</th></tr></thead>
                        <tbody>
                        @foreach ($domainCheck->ai_crawlers['crawlers'] as $token => $info)
                            <tr>
                                <td>{{ $token }}</td>
                                <td>{{ $info['label'] }}</td>
                                <td>
                                    @if ($info['allowed'])
                                        <span class="badge badge-ok">Acik</span>
                                    @else
                                        <span class="badge badge-warn">Engelli</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p class="muted">Kaynak: <a href="{{ $domainCheck->ai_crawlers['url'] }}" target="_blank" rel="noopener">{{ $domainCheck->ai_crawlers['url'] }}</a></p>
                </details>
            @else
                <p class="muted">robots.txt bulunamadi ({{ $domainCheck->ai_crawlers['url'] }}) - varsayilan olarak tum crawler'lar icin acik kabul edilir.</p>
            @endif

            <details>
                <summary>Sitemap</summary>
                @if ($domainCheck->sitemap['found'] ?? false)
                    @if ($domainCheck->sitemap['is_valid_xml'] ?? false)
                        <p>
                            {{ $domainCheck->sitemap['is_sitemap_index'] ? 'Sitemap index' : 'Sitemap' }},
                            {{ $domainCheck->sitemap['url_count'] }}
                            {{ $domainCheck->sitemap['is_sitemap_index'] ? 'alt-sitemap' : 'URL' }} iceriyor.
                        </p>
                    @else
                        <p class="text-error">{{ $domainCheck->sitemap['error'] ?? 'Gecersiz XML.' }}</p>
                    @endif
                    <p class="muted"><a href="{{ $domainCheck->sitemap['url'] }}" target="_blank" rel="noopener">{{ $domainCheck->sitemap['url'] }}</a></p>
                @else
                    <p class="muted">{{ $domainCheck->sitemap['url'] ?? '' }} bulunamadi.</p>
                @endif
            </details>

            <details>
                <summary>llms.txt</summary>
                @if ($domainCheck->llms_txt['found'] ?? false)
                    @if ($domainCheck->llms_txt['preview'])
                        <pre>{{ $domainCheck->llms_txt['preview'] }}</pre>
                    @endif
                    <p class="muted"><a href="{{ $domainCheck->llms_txt['url'] }}" target="_blank" rel="noopener">{{ $domainCheck->llms_txt['url'] }}</a></p>
                @else
                    <p class="muted">{{ $domainCheck->llms_txt['url'] ?? '' }} bulunamadi.</p>
                @endif
            </details>

            <details>
                <summary>Guvenlik header'lari</summary>
                @if ($domainCheck->security_headers['reachable'] ?? false)
                    <table>
                        <thead><tr><th>Header</th><th>Deger</th></tr></thead>
                        <tbody>
                        @foreach ($domainCheck->security_headers['headers'] ?? [] as $name => $value)
                            <tr>
                                <td>{{ $name }}</td>
                                <td>
                                    @if ($value)
                                        {{ $value }}
                                    @else
                                        <span class="badge badge-warn">yok</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>HTTP &rarr; HTTPS yonlendirme</td>
                            <td>
                                @if ($domainCheck->security_headers['http_redirects_to_https'] ?? false)
                                    <span class="badge badge-ok">var</span>
                                @else
                                    <span class="badge badge-warn">yok</span>
                                @endif
                            </td>
                        </tr>
                        </tbody>
                    </table>
                @else
                    <p class="text-error">Domaine erisilemedi.</p>
                @endif
            </details>
        </div>
    @endif

    <h2>Anahtar Kelime Ekle</h2>
    <form method="post" action="{{ route('keywords.store', $domain) }}" class="stacked-form">
        @csrf
        <label>Anahtar kelime
            <input type="text" name="keyword" required>
        </label>
        <label>On-page analiz sayfasi (opsiyonel, bos birakilirsa https://{{ $domain->domain }}/ kullanilir)
            <input type="url" name="url" placeholder="https://{{ $domain->domain }}/sayfa">
        </label>
        <button type="submit">Ekle</button>
    </form>

    <h2>Anahtar Kelimeler</h2>
    <p class="muted">"Kontrol Et" butonu SERP + on-page + Lighthouse taramasini ayni anda calistirir; bu 20-60 saniye surebilir, sayfa kapanmadan bekleyin.</p>

    @if ($domain->keywords->isEmpty())
        <p class="muted">Henuz anahtar kelime eklenmedi.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Kelime</th>
                    <th>Sayfa</th>
                    <th>Son siralama</th>
                    <th>AI Overview</th>
                    <th>Lighthouse (Perf/SEO/Eris/BP)</th>
                    <th>Son kontrol</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domain->keywords as $kw)
                    @php($last = $kw->latestCheck)
                    <tr>
                        <td><a href="{{ route('keywords.show', $kw) }}">{{ $kw->keyword }}</a></td>
                        <td class="muted">{{ $kw->url ?: ('https://' . $domain->domain . '/') }}</td>
                        <td>
                            @if (!$last)
                                <span class="muted">-</span>
                            @elseif ($last->blocked)
                                <span class="badge badge-warn">engellendi</span>
                            @elseif ($last->target_position)
                                <strong>{{ $last->target_position }}</strong>
                            @else
                                <span class="muted">bulunamadi</span>
                            @endif
                        </td>
                        <td>
                            @if (!$last)
                                <span class="muted">-</span>
                            @elseif ($last->ai_overview_present)
                                @if ($last->ai_overview_target_cited)
                                    <span class="badge badge-ok">var, kaynakta</span>
                                @else
                                    <span class="badge badge-warn">var, kaynakta degil</span>
                                @endif
                            @else
                                <span class="muted">yok</span>
                            @endif
                        </td>
                        <td class="muted">
                            @if (!$last)
                                -
                            @else
                                {{ $last->lighthouse_performance ?? '-' }} /
                                {{ $last->lighthouse_seo ?? '-' }} /
                                {{ $last->lighthouse_accessibility ?? '-' }} /
                                {{ $last->lighthouse_best_practices ?? '-' }}
                            @endif
                        </td>
                        <td class="muted">{{ $last?->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="actions">
                            <form method="post" action="{{ route('keywords.check', $kw) }}">
                                @csrf
                                <button type="submit">Kontrol Et</button>
                            </form>
                            <form method="post" action="{{ route('keywords.destroy', $kw) }}" onsubmit="return confirm('Bu anahtar kelime silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
