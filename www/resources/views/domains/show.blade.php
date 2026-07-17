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
                @php($domainCheck = $domain->latestDomainCheck)

                <div class="text-secondary small mb-2">Son kontrol: {{ $domainCheck->created_at->format('Y-m-d H:i:s') }}</div>
                <div class="mb-3">
                    @include('domains._check-badges', ['domainCheck' => $domainCheck])
                </div>

                <div class="accordion" id="site-check-accordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ac-crawlers">
                                AI crawler erişimi (robots.txt)
                            </button>
                        </h2>
                        <div id="ac-crawlers" class="accordion-collapse collapse" data-bs-parent="#site-check-accordion">
                            <div class="accordion-body">
                                @if ($domainCheck->ai_crawlers['found'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Crawler</th><th>Kim</th><th>Durum</th></tr></thead>
                                            <tbody>
                                            @foreach ($domainCheck->ai_crawlers['crawlers'] as $token => $info)
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
                                    <p class="text-secondary mb-0">Kaynak: <a href="{{ $domainCheck->ai_crawlers['url'] }}" target="_blank" rel="noopener">{{ $domainCheck->ai_crawlers['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">robots.txt bulunamadı ({{ $domainCheck->ai_crawlers['url'] }}) — varsayılan olarak tüm crawler'lar için açık kabul edilir.</p>
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
                                @if ($domainCheck->sitemap['found'] ?? false)
                                    @if ($domainCheck->sitemap['is_valid_xml'] ?? false)
                                        <p>
                                            {{ $domainCheck->sitemap['is_sitemap_index'] ? 'Sitemap index' : 'Sitemap' }},
                                            {{ $domainCheck->sitemap['url_count'] }}
                                            {{ $domainCheck->sitemap['is_sitemap_index'] ? 'alt-sitemap' : 'URL' }} içeriyor.
                                        </p>
                                    @else
                                        <p class="text-danger">{{ $domainCheck->sitemap['error'] ?? 'Geçersiz XML.' }}</p>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $domainCheck->sitemap['url'] }}" target="_blank" rel="noopener">{{ $domainCheck->sitemap['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ $domainCheck->sitemap['url'] ?? '' }} bulunamadı.</p>
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
                                @if ($domainCheck->llms_txt['found'] ?? false)
                                    @if ($domainCheck->llms_txt['preview'])
                                        <pre class="bg-body-secondary p-2 rounded">{{ $domainCheck->llms_txt['preview'] }}</pre>
                                    @endif
                                    <p class="text-secondary mb-0"><a href="{{ $domainCheck->llms_txt['url'] }}" target="_blank" rel="noopener">{{ $domainCheck->llms_txt['url'] }}</a></p>
                                @else
                                    <p class="text-secondary mb-0">{{ $domainCheck->llms_txt['url'] ?? '' }} bulunamadı.</p>
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
                                @if ($domainCheck->security_headers['reachable'] ?? false)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Header</th><th>Değer</th></tr></thead>
                                            <tbody>
                                            @foreach ($domainCheck->security_headers['headers'] ?? [] as $name => $value)
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
                                                    @if ($domainCheck->security_headers['http_redirects_to_https'] ?? false)
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
                </div>
            @else
                <p class="text-secondary mb-0">Henüz site kontrolü çalıştırılmadı.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Anahtar Kelimeler</h3>
        </div>
        <div class="card-body border-bottom py-2">
            <p class="text-secondary mb-0">"Kontrol Et" butonu SERP + on-page + Lighthouse taramasını aynı anda çalıştırır; bu 20-60 saniye sürebilir, sayfa kapanmadan bekleyin.</p>
        </div>

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
                            @php($last = $kw->latestCheck)
                            <tr>
                                <td><a href="{{ route('keywords.show', $kw) }}" class="fw-medium">{{ $kw->keyword }}</a></td>
                                <td class="text-secondary">{{ $kw->url ?: ('https://' . $domain->domain . '/') }}</td>
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
