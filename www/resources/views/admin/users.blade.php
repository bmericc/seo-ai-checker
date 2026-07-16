@extends('layout')

@section('title', 'Kullanıcılar')

@section('content')
    <h1>Kullanıcılar</h1>

    <table>
        <thead>
            <tr>
                <th>E-posta</th>
                <th>Ad</th>
                <th>Durum</th>
                <th>Admin</th>
                <th>Kayıt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->name }}</td>
                    <td>
                        @if ($user->isApproved())
                            <span class="badge badge-ok">Onaylı</span>
                        @else
                            <span class="badge badge-warn">Onay bekliyor</span>
                        @endif
                    </td>
                    <td>
                        @if ($user->is_admin)
                            <span class="badge badge-ok">Admin</span>
                        @endif
                    </td>
                    <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="actions">
                        @if ($user->isApproved())
                            <form method="post" action="{{ route('admin.users.revoke', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-danger">Onayı kaldır</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.users.approve', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit">Onayla</button>
                            </form>
                        @endif

                        @if ($user->id !== auth()->id())
                            <form method="post" action="{{ route('admin.users.toggle-admin', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit">{{ $user->is_admin ? 'Adminliği kaldır' : 'Admin yap' }}</button>
                            </form>

                            <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ $user->email }} silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Sil</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
