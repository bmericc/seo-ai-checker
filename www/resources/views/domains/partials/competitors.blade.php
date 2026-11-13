{{--
    Rakipler sekmesi: (1) secilen/elle eklenen rakipler, (2) SERP kesfi
    (sec / rakip degil butonlariyla), (3) capraz kontrol - secili
    rakiplerin one ciktigi ama bizim listemizde olmayan anahtar kelimeler.
    Degiskenler DomainController::show'dan gelir.
--}}
@php
    $trackedSet = $trackedCompetitors->pluck('competitor_domain')->flip();
    $anyFetching = $trackedCompetitors->contains(fn ($c) => $c->isFetchingKeywords());
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">{{ __('Seçili Rakipler') }}</h3>
    </div>
    <div class="card-body">
        <p class="text-secondary">
            {{ __('Gerçek rakiplerinizi seçin ya da elle ekleyin; öne çıktıkları ve sizin listenizde olmayan anahtar kelimeler aşağıda çapraz kontrol edilir.') }}
            @if ($dataForSeoConfigured)
                {{ __('Kaynak: DataForSEO sıralama verisi (ilk 20 pozisyon); veri yoksa rakip sitesi taranır.') }}
            @else
                {{ __('Kaynak: rakip sitesinin ana sayfa ve sitemap taraması (DataForSEO yapılandırılmadığı için sıralama/hacim verisi yok).') }}
            @endif
        </p>

        @if ($canEdit)
            <form method="post" action="{{ route('domains.competitors.store', $domain) }}" class="row g-2 mb-3">
                @csrf
                <input type="hidden" name="status" value="tracked">
                <input type="hidden" name="source" value="manual">
                <div class="col-12 col-md-6">
                    <input type="text" name="competitor_domain" class="form-control" placeholder="{{ __('rakip.com') }}" required maxlength="255" aria-label="{{ __('Rakip domain') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-plus icon"></i> {{ __('Rakip ekle') }}</button>
                </div>
            </form>
        @endif

        @if ($trackedCompetitors->isEmpty())
            <p class="text-secondary mb-0">{{ __('Henüz seçili rakip yok. Aşağıdaki SERP keşfinden seçebilir ya da elle ekleyebilirsiniz.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>{{ __('Kaynak') }}</th>
                            <th>{{ __('Anahtar kelime') }}</th>
                            <th>{{ __('Son güncelleme') }}</th>
                            @if ($canEdit)
                                <th class="w-1"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trackedCompetitors as $competitor)
                            <tr>
                                <td class="fw-medium">
                                    <a href="{{ $competitor->rootUrl() }}" target="_blank" rel="noopener noreferrer">{{ $competitor->competitor_domain }}</a>
                                    <div class="text-secondary small">{{ $competitor->source === 'serp' ? __('SERP keşfinden seçildi') : __('Elle eklendi') }}</div>
                                </td>
                                <td>
                                    @if ($competitor->keywords_source === 'dataforseo')
                                        <span class="badge bg-blue-lt">DataForSEO</span>
                                    @elseif ($competitor->keywords_source === 'crawl')
                                        <span class="badge bg-secondary-lt">{{ __('Site taraması') }}</span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($competitor->isFetchingKeywords())
                                        <span class="d-inline-flex align-items-center gap-2 text-secondary small">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            {{ __('Çekiliyor…') }}
                                        </span>
                                    @else
                                        {{ count($competitor->keywords ?? []) }}
                                    @endif
                                    @if ($competitor->keywords_error)
                                        <div class="text-warning small">{{ $competitor->keywords_error }}</div>
                                    @endif
                                </td>
                                <td class="text-secondary">{{ $competitor->keywords_fetched_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                @if ($canEdit)
                                    <td>
                                        <div class="btn-list flex-nowrap">
                                            <form method="post" action="{{ route('domains.competitors.refresh', [$domain, $competitor]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Anahtar kelimeleri yeniden çek') }}" @disabled($competitor->isFetchingKeywords())>
                                                    <i class="ti ti-refresh"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="{{ route('domains.competitors.update', [$domain, $competitor]) }}">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="status" value="ignored">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Rakip değil') }}">
                                                    <i class="ti ti-eye-off"></i>
                                                </button>
                                            </form>
                                            <form method="post" action="{{ route('domains.competitors.destroy', [$domain, $competitor]) }}" onsubmit="return confirm(@js(__(':domain rakip listesinden kaldırılsın mı?', ['domain' => $competitor->competitor_domain])))">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Kaldır') }}">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">{{ __('Rakip Anahtar Kelime Boşlukları') }}</h3>
    </div>
    @if ($trackedCompetitors->isEmpty())
        <div class="card-body">
            <p class="text-secondary mb-0">{{ __('Çapraz kontrol için en az bir rakip seçin.') }}</p>
        </div>
    @elseif (empty($competitorKeywordGap))
        <div class="card-body">
            <p class="text-secondary mb-0">
                @if ($anyFetching)
                    {{ __('Rakiplerin anahtar kelimeleri çekiliyor; sayfa birazdan kendini yenileyecek.') }}
                @else
                    {{ __('Seçili rakiplerin öne çıktığı ve sizin listenizde olmayan anahtar kelime bulunamadı.') }}
                @endif
            </p>
        </div>
    @else
        <div class="card-body pb-0">
            <p class="text-secondary">{{ __('Seçili rakiplerin öne çıktığı ama sizin takip listenizde olmayan anahtar kelimeler. Birden çok rakipte ortak olanlar önce gelir.') }}</p>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>{{ __('Anahtar kelime') }}</th>
                        <th>{{ __('Rakipler') }}</th>
                        <th>{{ __('Aylık hacim') }}</th>
                        <th>{{ __('En iyi rakip pozisyonu') }}</th>
                        @if ($canEdit)
                            <th class="w-1"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($competitorKeywordGap as $gap)
                        <tr>
                            <td class="fw-medium">{{ $gap['keyword'] }}</td>
                            <td>
                                @foreach ($gap['competitors'] as $entry)
                                    <span class="badge bg-secondary-lt me-1 mb-1" @if ($entry['url']) title="{{ $entry['url'] }}" @endif>
                                        {{ $entry['domain'] }}@if ($entry['position'] !== null) · #{{ $entry['position'] }}@endif
                                    </span>
                                @endforeach
                            </td>
                            <td class="text-secondary">{{ $gap['search_volume'] !== null ? number_format($gap['search_volume']) : '—' }}</td>
                            <td class="text-secondary">{{ $gap['best_position'] ?? '—' }}</td>
                            @if ($canEdit)
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <form method="post" action="{{ route('keywords.store', $domain) }}">
                                            @csrf
                                            <input type="hidden" name="keyword" value="{{ $gap['keyword'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('Takip listesine ekle') }}">
                                                <i class="ti ti-plus"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('domains.keyword-suggestions.dismiss', $domain) }}">
                                            @csrf
                                            <input type="hidden" name="phrase" value="{{ $gap['keyword'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Öneriyi kaldır: :phrase', ['phrase' => $gap['keyword']]) }}">
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">{{ __('Rakip Keşfi (SERP)') }}</h3>
    </div>
    <div class="card-body">
        @if ($competitorAnalysis['tracked_keyword_count'] === 0)
            <p class="text-secondary mb-0">{{ __('Rakip analizi için en az bir anahtar kelimede engellenmemiş bir SERP kontrolü gerekir.') }}</p>
        @elseif (empty($competitorAnalysis['competitors']))
            <p class="text-secondary mb-0">{{ __('İzlenen anahtar kelimelerin SERP sonuçlarında tekrar eden bir rakip domain bulunamadı.') }}</p>
        @else
            <p class="text-secondary">
                {{ __('İzlenen :count anahtar kelimenin son SERP sonuçlarında sizinle birlikte en sık çıkan domainler:', ['count' => $competitorAnalysis['tracked_keyword_count']]) }}
            </p>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>{{ __('Ortak anahtar kelime') }}</th>
                            <th>{{ __('Ortalama pozisyon') }}</th>
                            <th>{{ __('En iyi pozisyon') }}</th>
                            @if ($canEdit)
                                <th class="w-1"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($competitorAnalysis['competitors'] as $competitor)
                            @php
                                $isTracked = $trackedSet->has(\App\Support\Domain::normalize($competitor['domain']));
                            @endphp
                            <tr>
                                <td class="fw-medium">
                                    {{ $competitor['domain'] }}
                                    @if ($isTracked)
                                        <span class="badge bg-green-lt ms-1">{{ __('seçili') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="{{ implode(', ', $competitor['keywords']) }}"
                                        style="cursor: help; border-bottom: 1px dotted currentColor;"
                                    >{{ __(':count / :total', ['count' => $competitor['keyword_count'], 'total' => $competitorAnalysis['tracked_keyword_count']]) }}</span>
                                </td>
                                <td class="text-secondary">{{ $competitor['average_position'] }}</td>
                                <td class="text-secondary">{{ $competitor['best_position'] }}</td>
                                @if ($canEdit)
                                    <td>
                                        @if (! $isTracked)
                                            <div class="btn-list flex-nowrap">
                                                <form method="post" action="{{ route('domains.competitors.store', $domain) }}">
                                                    @csrf
                                                    <input type="hidden" name="competitor_domain" value="{{ $competitor['domain'] }}">
                                                    <input type="hidden" name="status" value="tracked">
                                                    <input type="hidden" name="source" value="serp">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="ti ti-check icon"></i> {{ __('Rakip olarak seç') }}
                                                    </button>
                                                </form>
                                                <form method="post" action="{{ route('domains.competitors.store', $domain) }}">
                                                    @csrf
                                                    <input type="hidden" name="competitor_domain" value="{{ $competitor['domain'] }}">
                                                    <input type="hidden" name="status" value="ignored">
                                                    <input type="hidden" name="source" value="serp">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                        {{ __('Rakip değil') }}
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($ignoredCompetitors->isNotEmpty())
            <details class="mt-3">
                <summary class="text-secondary small">{{ __('Rakip değil olarak işaretlenenler (:count)', ['count' => $ignoredCompetitors->count()]) }}</summary>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($ignoredCompetitors as $ignored)
                        <div class="btn-group">
                            <span class="btn btn-sm btn-ghost-secondary disabled">{{ $ignored->competitor_domain }}</span>
                            @if ($canEdit)
                                <form method="post" action="{{ route('domains.competitors.destroy', [$domain, $ignored]) }}">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Geri al') }}">
                                        <i class="ti ti-arrow-back-up"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</div>

@if ($anyFetching)
    <script>
        // Rakip anahtar kelimeleri arka planda cekilirken sekme acikken sayfayi tazele.
        setTimeout(function () {
            var pane = document.getElementById('tab-competitors');
            if (pane && pane.classList.contains('active') && !document.hidden) {
                window.location.hash = 'tab-competitors';
                window.location.reload();
            }
        }, 20000);
    </script>
@endif
