@extends('layout')

@section('title', $domain->domain)

@section('content')
    <p><a href="{{ route('dashboard') }}">&larr; Domainler</a></p>
    <h1>{{ $domain->domain }}</h1>

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
