@php
    $permissionLabels = [
        \App\Models\DomainMember::READ => __('Salt okunur'),
        \App\Models\DomainMember::WRITE => __('Düzenleyebilir'),
    ];
@endphp

<div class="modal modal-blur fade" id="modal-domain-members" tabindex="-1" role="dialog" aria-hidden="true" @if (session('open_members_modal')) data-auto-open @endif>
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Domaine erişim') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Kapat') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary">{{ __('Ekip arkadaşlarınız aynı domaini tekrar eklemek yerine sizin kaydınızı görsün. Tüm geçmiş, anahtar kelimeler ve raporlar ortak kalır.') }}</p>

                <form method="post" action="{{ route('domains.members.store', $domain) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-sm">
                        <label class="form-label" for="member-email">{{ __('E-posta (Google hesabı)') }}</label>
                        <input type="email" id="member-email" name="email" class="form-control" required maxlength="255" placeholder="ornek@sirket.com">
                    </div>
                    <div class="col-8 col-sm-auto">
                        <label class="form-label" for="member-permission">{{ __('Yetki') }}</label>
                        <select id="member-permission" name="permission" class="form-select">
                            @foreach ($permissionLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4 col-sm-auto">
                        <button type="submit" class="btn btn-primary w-100"><i class="ti ti-user-plus icon"></i> {{ __('Ekle') }}</button>
                    </div>
                </form>
                <div class="form-hint mt-2">
                    {{ __('Salt okunur: her şeyi görür, hiçbir şeyi değiştiremez. Düzenleyebilir: kontrol çalıştırır, anahtar kelime ekler/siler, rapor linki oluşturur. Domaini silmek ve erişimi yönetmek yalnızca sahibe aittir.') }}
                </div>

                <hr>
                <div class="form-label">{{ __('Erişimi olanlar') }}</div>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2 border rounded p-2">
                        <i class="ti ti-crown text-warning"></i>
                        <span class="text-truncate flex-fill" style="min-width: 0">{{ $domain->user?->email ?? '—' }}</span>
                        <span class="badge bg-secondary-lt">{{ __('Sahip') }}</span>
                    </div>
                    @foreach ($domain->members as $member)
                        <div class="d-flex align-items-center gap-2 border rounded p-2">
                            <i class="ti ti-user text-secondary"></i>
                            <div class="flex-fill" style="min-width: 0">
                                <div class="text-truncate">{{ $member->email }}</div>
                                @if ($member->isPending())
                                    <div class="small text-warning">{{ __('Bekliyor') }} · {{ __('Bu adresle henüz giriş yapılmadı.') }}</div>
                                @endif
                            </div>
                            <form method="post" action="{{ route('domains.members.update', [$domain, $member]) }}">
                                @csrf
                                @method('PATCH')
                                <select name="permission" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="{{ __('Yetki: :email', ['email' => $member->email]) }}">
                                    @foreach ($permissionLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($member->permission === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <form method="post" action="{{ route('domains.members.destroy', [$domain, $member]) }}" onsubmit="return confirm({{ json_encode(__(':email erişimi kaldırılsın mı?', ['email' => $member->email])) }});">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-icon btn-sm btn-ghost-danger" aria-label="{{ __('Erişimi kaldır') }}" title="{{ __('Erişimi kaldır') }}"><i class="ti ti-user-minus"></i></button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Kapat') }}</button>
            </div>
        </div>
    </div>
</div>
