@php
    $activeShares = $domain->reportShares()->active()->get();
    $sectionLabels = \App\Models\ReportShare::sectionLabels();
@endphp

<div class="modal modal-blur fade" id="modal-share-report" tabindex="-1" role="dialog" aria-hidden="true" @if (session('open_share_modal')) data-auto-open @endif>
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Raporu paylaş') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Kapat') }}"></button>
            </div>
            <form method="post" action="{{ route('domains.shares.store', $domain) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-secondary">{{ __('Müşterinize ya da ekibinize giriş yapmadan açabilecekleri, salt okunur bir rapor linki gönderin. Rapor her açılışta güncel veriyi gösterir.') }}</p>
                    <div class="mb-3">
                        <label class="form-label" for="share-title">{{ __('Başlık (isteğe bağlı)') }}</label>
                        <input type="text" id="share-title" name="title" class="form-control" maxlength="120" placeholder="{{ __(':domain SEO raporu', ['domain' => $domain->domain]) }}">
                    </div>
                    <div class="mb-3">
                        <div class="form-label">{{ __('Raporda neler olsun?') }}</div>
                        @foreach ($sectionLabels as $key => $label)
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="{{ $key }}" checked>
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div>
                        <label class="form-label" for="share-expiry">{{ __('Link ne kadar geçerli?') }}</label>
                        <select id="share-expiry" name="expires_in" class="form-select">
                            <option value="7">{{ __('7 gün') }}</option>
                            <option value="30" selected>{{ __('30 gün') }}</option>
                            <option value="90">{{ __('90 gün') }}</option>
                            <option value="">{{ __('Süresiz') }}</option>
                        </select>
                    </div>

                    @if ($activeShares->isNotEmpty())
                        <hr>
                        <div class="form-label">{{ __('Açık linkler') }}</div>
                        <div class="d-flex flex-column gap-2">
                            @foreach ($activeShares as $share)
                                <div class="border rounded p-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-medium text-truncate flex-fill" style="min-width: 0">{{ $share->title ?: $domain->domain }}</span>
                                        <span class="badge bg-secondary-lt">{{ trans_choice('{0} hiç açılmadı|{1} 1 kez açıldı|[2,*] :count kez açıldı', $share->view_count, ['count' => $share->view_count]) }}</span>
                                    </div>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="text" class="form-control" value="{{ $share->url() }}" readonly onclick="this.select()">
                                        <button type="button" class="btn" data-copy-text="{{ $share->url() }}" data-copied-label="{{ __('Kopyalandı') }}">{{ __('Kopyala') }}</button>
                                        <a href="{{ $share->url() }}" target="_blank" rel="noopener" class="btn" aria-label="{{ __('Aç') }}"><i class="ti ti-external-link"></i></a>
                                        <button type="submit" form="revoke-share-{{ $share->id }}" class="btn text-danger" aria-label="{{ __('Linki kapat') }}" title="{{ __('Linki kapat') }}"><i class="ti ti-link-off"></i></button>
                                    </div>
                                    <div class="small text-secondary mt-1">
                                        {{ $share->expires_at ? __(':date tarihine kadar geçerli', ['date' => $share->expires_at->translatedFormat('d M Y')]) : __('Süresiz') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Kapat') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-link icon"></i> {{ __('Link oluştur') }}</button>
                </div>
            </form>
            @foreach ($activeShares as $share)
                <form id="revoke-share-{{ $share->id }}" method="post" action="{{ route('domains.shares.destroy', [$domain, $share]) }}" onsubmit="return confirm({{ json_encode(__('Bu link kapatılsın mı? Linki açan herkes raporu artık göremez.')) }});" class="d-none">
                    @csrf
                    @method('delete')
                </form>
            @endforeach
        </div>
    </div>
</div>
