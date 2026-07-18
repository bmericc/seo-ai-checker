@extends('layout')

@section('title', __('Domainler'))

@section('page-title', __('Domainler'))
@section('page-actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-domain">
        <i class="ti ti-plus icon"></i> {{ __('Domain Ekle') }}
    </button>
@endsection

@section('content')
    <div class="card">
        @if ($domains->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-world fs-1"></i></div>
                <p class="empty-title">{{ __('Henüz domain eklenmedi') }}</p>
                <p class="empty-subtitle text-secondary">{{ __('Takibe başlamak için bir domain ekleyin.') }}</p>
                <div class="empty-action">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-domain">
                        <i class="ti ti-plus icon"></i> {{ __('Domain Ekle') }}
                    </button>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>{{ __('Domain') }}</th>
                            @if (auth()->user()->is_admin)
                                <th>{{ __('Sahibi') }}</th>
                            @endif
                            <th>{{ __('Site Kontrolü') }}</th>
                            <th>{{ __('Anahtar kelime sayısı') }}</th>
                            <th>{{ __('Eklenme') }}</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($domains as $domain)
                            <tr>
                                <td><a href="{{ route('domains.show', $domain) }}" class="fw-medium">{{ $domain->domain }}</a></td>
                                @if (auth()->user()->is_admin)
                                    <td class="text-secondary">{{ $domain->user?->email ?? '—' }}</td>
                                @endif
                                <td>
                                    @if ($domain->latestDomainCheck)
                                        @include('domains._check-badges', ['domainCheck' => $domain->latestDomainCheck])
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-azure-lt">{{ $domain->keywords_count }}</span></td>
                                <td class="text-secondary">{{ $domain->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form method="post" action="{{ route('domains.destroy', $domain) }}" onsubmit="return confirm('{{ __(':domain silinsin mi? Tüm anahtar kelimeler ve geçmiş de silinecek.', ['domain' => $domain->domain]) }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost-danger btn-icon" aria-label="{{ __('Sil') }}">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="modal modal-blur fade" id="modal-add-domain" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="{{ route('domains.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Domain Ekle') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-0">
                            <label class="form-label">{{ __('Domain') }}</label>
                            <input type="text" name="domain" class="form-control" placeholder="example.com" required autofocus>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('İptal') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-plus icon"></i> {{ __('Ekle') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
