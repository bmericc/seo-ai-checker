@extends('layout')

@section('title', __('Kullanıcılar'))
@section('page-title', __('Kullanıcılar'))

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>{{ __('E-posta') }}</th>
                        <th>{{ __('Ad') }}</th>
                        <th>{{ __('Durum') }}</th>
                        <th>{{ __('Admin') }}</th>
                        <th>{{ __('Kayıt') }}</th>
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
                                    <span class="badge bg-success-lt">{{ __('Onaylı') }}</span>
                                @else
                                    <span class="badge bg-warning-lt">{{ __('Onay bekliyor') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->is_admin)
                                    <span class="badge bg-azure-lt">{{ __('Admin') }}</span>
                                @endif
                            </td>
                            <td class="text-secondary">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    @if ($user->isApproved())
                                        <form method="post" action="{{ route('admin.users.revoke', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Onayı kaldır') }}</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('admin.users.approve', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-success btn-sm">{{ __('Onayla') }}</button>
                                        </form>
                                    @endif

                                    @if ($user->id !== auth()->id())
                                        <form method="post" action="{{ route('admin.users.toggle-admin', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $user->is_admin ? __('Adminliği kaldır') : __('Admin yap') }}</button>
                                        </form>

                                        <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ __(':email silinsin mi?', ['email' => $user->email]) }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-ghost-danger btn-sm" aria-label="{{ __('Sil') }}">
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
