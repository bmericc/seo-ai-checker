@extends('layout')

@section('title', 'Kullanıcılar')
@section('page-title', 'Kullanıcılar')

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>E-posta</th>
                        <th>Ad</th>
                        <th>Durum</th>
                        <th>Admin</th>
                        <th>Kayıt</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->name }}</td>
                            <td>
                                @if ($user->isApproved())
                                    <span class="badge bg-success-lt">Onaylı</span>
                                @else
                                    <span class="badge bg-warning-lt">Onay bekliyor</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->is_admin)
                                    <span class="badge bg-azure-lt">Admin</span>
                                @endif
                            </td>
                            <td class="text-secondary">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    @if ($user->isApproved())
                                        <form method="post" action="{{ route('admin.users.revoke', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Onayı kaldır</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('admin.users.approve', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-success btn-sm">Onayla</button>
                                        </form>
                                    @endif

                                    @if ($user->id !== auth()->id())
                                        <form method="post" action="{{ route('admin.users.toggle-admin', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $user->is_admin ? 'Adminliği kaldır' : 'Admin yap' }}</button>
                                        </form>

                                        <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ $user->email }} silinsin mi?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-ghost-danger btn-sm" aria-label="Sil">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
