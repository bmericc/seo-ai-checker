@extends('layout')

@section('title', 'Domainler')

@section('content')
    <h1>Takip Edilen Domainler</h1>

    <form method="post" action="{{ route('domains.store') }}" class="inline-form">
        @csrf
        <input type="text" name="domain" placeholder="example.com" required>
        <button type="submit">Domain Ekle</button>
    </form>

    @if ($domains->isEmpty())
        <p class="muted">Henuz domain eklenmedi.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Anahtar kelime sayisi</th>
                    <th>Eklenme</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domains as $domain)
                    <tr>
                        <td><a href="{{ route('domains.show', $domain) }}">{{ $domain->domain }}</a></td>
                        <td>{{ $domain->keywords_count }}</td>
                        <td>{{ $domain->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            <form method="post" action="{{ route('domains.destroy', $domain) }}" onsubmit="return confirm('{{ $domain->domain }} silinsin mi? Tum anahtar kelimeler ve gecmis de silinecek.');">
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
