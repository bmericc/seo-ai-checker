@extends('layout')

@section('title', $keyword->keyword)

@section('content')
    <p><a href="{{ route('domains.show', $keyword->domain) }}">&larr; {{ $keyword->domain->domain }}</a></p>
    <h1>{{ $keyword->keyword }}</h1>
    <p class="muted">Sayfa: {{ $keyword->targetUrl() }}</p>

    <form method="post" action="{{ route('keywords.check', $keyword) }}">
        @csrf
        <button type="submit">Kontrol Et</button>
    </form>

    <h2>Gecmis</h2>
    @if ($keyword->checks->isEmpty())
        <p class="muted">Henuz kontrol calistirilmadi.</p>
    @else
        @foreach ($keyword->checks as $check)
            <div class="check-card">
                <div class="check-card-header">
                    <strong>{{ $check->created_at->format('Y-m-d H:i:s') }}</strong>
                    @if ($check->blocked)
                        <span class="badge badge-warn">Google engellendi</span>
                    @elseif ($check->target_position)
                        <span class="badge badge-ok">Siralama: {{ $check->target_position }}</span>
                    @else
                        <span class="badge">Ilk sonuclarda yok</span>
                    @endif

                    @if ($check->ai_overview_present)
                        @if ($check->ai_overview_target_cited)
                            <span class="badge badge-ok">AI Overview: kaynakta</span>
                        @else
                            <span class="badge badge-warn">AI Overview: var, kaynakta degil</span>
                        @endif
                    @else
                        <span class="badge">AI Overview yok</span>
                    @endif
                </div>

                @if ($check->blocked)
                    <p class="text-error">{{ $check->block_reason }}</p>
                @endif

                @if (!empty($check->organic_results))
                    <details>
                        <summary>Organik sonuclar ({{ count($check->organic_results) }})</summary>
                        <table>
                            <thead><tr><th>#</th><th>Domain</th><th>Baslik</th></tr></thead>
                            <tbody>
                            @foreach ($check->organic_results as $r)
                                <tr><td>{{ $r['position'] }}</td><td>{{ $r['domain'] }}</td><td>{{ $r['title'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif

                @if ($check->ai_overview_present)
                    <p class="muted">
                        AI Overview kaynak domainler:
                        @if (!empty($check->ai_overview_cited_domains))
                            {{ implode(', ', $check->ai_overview_cited_domains) }}
                        @else
                            (otomatik cikarilamadi){{ $check->ai_overview_note ? ' - ' . $check->ai_overview_note : '' }}
                        @endif
                    </p>
                @endif

                @if ($check->onpage)
                    <details>
                        <summary>On-page SEO detayi</summary>
                        <table>
                            <tr><th>Title</th><td>{{ $check->onpage['title'] ?: '(yok)' }} ({{ $check->onpage['title_length'] }} karakter)</td></tr>
                            <tr><th>Meta description</th><td>{{ $check->onpage['meta_description'] ?: '(yok)' }} ({{ $check->onpage['meta_description_length'] }} karakter)</td></tr>
                            <tr><th>H1</th><td>{{ !empty($check->onpage['h1s']) ? implode(', ', $check->onpage['h1s']) : '(yok)' }}</td></tr>
                            <tr><th>H2 sayisi</th><td>{{ $check->onpage['h2_count'] }}</td></tr>
                            <tr><th>Kelime sayisi</th><td>{{ $check->onpage['word_count'] }}</td></tr>
                            <tr><th>Anahtar kelime yogunlugu</th><td>{{ $check->onpage['keyword_density_percent'] }}%</td></tr>
                            <tr><th>Alt etiketi eksik gorsel</th><td>{{ $check->onpage['images_missing_alt'] }}</td></tr>
                            <tr><th>Ic/dis link</th><td>{{ $check->onpage['internal_links'] }} / {{ $check->onpage['external_links'] }}</td></tr>
                            <tr><th>Yapisal veri</th><td>{{ $check->onpage['has_structured_data'] ? 'var' : 'yok' }}</td></tr>
                        </table>
                    </details>
                @elseif ($check->onpage_error)
                    <p class="text-error">On-page analiz basarisiz: {{ $check->onpage_error }}</p>
                @endif

                <div class="lighthouse-scores">
                    <span>Lighthouse:</span>
                    @if ($check->lighthouse_error)
                        <span class="text-error">{{ $check->lighthouse_error }}</span>
                    @else
                        <span class="badge">Performans {{ $check->lighthouse_performance ?? '-' }}</span>
                        <span class="badge">SEO {{ $check->lighthouse_seo ?? '-' }}</span>
                        <span class="badge">Erisilebilirlik {{ $check->lighthouse_accessibility ?? '-' }}</span>
                        <span class="badge">Best Practices {{ $check->lighthouse_best_practices ?? '-' }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
@endsection
