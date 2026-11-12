{{--
    Anahtar kelime onerileri karti. Kaynak: site geneli birlestirilmis liste
    (domains.site_keyword_suggestions, CrawlSiteKeywordCandidates arka planda
    doldurur); henuz yoksa son domain kontrolundeki ana sayfa onerileri.
    Tarama surerken domains.keyword-suggestions endpoint'i bu partial'i
    yeniden render eder ve show.blade.php'deki kucuk script icerigi degistirir.
--}}
@php
    $trackedKeywords = $domain->keywords()->pluck('keyword')->map(fn ($k) => mb_strtolower(trim($k)));
    $dismissedSuggestions = collect($domain->dismissed_keyword_suggestions ?? []);
    $siteSuggestions = $domain->site_keyword_suggestions ?? [];
    $isSiteWide = $siteSuggestions !== [];
    $suggestedKeywords = collect($isSiteWide ? $siteSuggestions : ($domain->latestDomainCheck?->suggested_keywords ?? []))
        ->reject(fn ($s) => $trackedKeywords->contains(mb_strtolower($s['phrase'])))
        ->reject(fn ($s) => $dismissedSuggestions->contains(mb_strtolower($s['phrase'])))
        ->filter(fn ($s) => \App\Services\Keywords\KeywordSuggester::isMultiWord((string) $s['phrase']));
    $scan = $domain->site_keyword_scan ?? [];
    $scanRunning = ($scan['status'] ?? null) === 'running';
    $scanTotal = (int) ($scan['total'] ?? 0);
    $scanScanned = min((int) ($scan['scanned'] ?? 0), $scanTotal);
    $pagesWithData = (int) ($scan['pages_with_data'] ?? 0);
@endphp
@if ($suggestedKeywords->isNotEmpty() || $scanRunning)
    <div class="card-body border-bottom py-3">
        @if ($scanRunning)
            <div class="d-flex align-items-center gap-2 text-secondary small mb-2">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                @if ($scanTotal > 0)
                    {{ __('Site taranıyor: :scanned / :total sayfa — öneriler güncelleniyor', ['scanned' => $scanScanned, 'total' => $scanTotal]) }}
                @else
                    {{ __('Site taranıyor — öneriler güncelleniyor') }}
                @endif
            </div>
        @endif

        @if ($suggestedKeywords->isNotEmpty())
            <div class="text-secondary small mb-2">
                @if ($isSiteWide && $pagesWithData > 0)
                    {{ __('Ana sayfa ve :count site sayfasından otomatik çıkarılan anahtar kelime önerileri — eklemek için "+", istemiyorsanız "×" tıklayın:', ['count' => $pagesWithData]) }}
                @else
                    {{ __('Ana sayfa HTML\'inden otomatik çıkarılan anahtar kelime önerileri — eklemek için "+", istemiyorsanız "×" tıklayın:') }}
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($suggestedKeywords as $suggestion)
                    @php
                        $pages = (int) ($suggestion['pages'] ?? 1);
                        $topUrl = $suggestion['url'] ?? null;
                    @endphp
                    <div class="btn-group">
                        <form method="post" action="{{ route('keywords.store', $domain) }}">
                            @csrf
                            <input type="hidden" name="keyword" value="{{ $suggestion['phrase'] }}">
                            @if ($topUrl)
                                <input type="hidden" name="url" value="{{ $topUrl }}">
                            @endif
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                @if ($topUrl) title="{{ __('En güçlü geçtiği sayfa: :url', ['url' => $topUrl]) }}" @endif>
                                <i class="ti ti-plus icon"></i> {{ $suggestion['phrase'] }}
                                @if ($pages > 1)
                                    <span class="badge bg-secondary-lt ms-1">{{ __(':count sayfa', ['count' => $pages]) }}</span>
                                @endif
                            </button>
                        </form>
                        <form method="post" action="{{ route('domains.keyword-suggestions.dismiss', $domain) }}">
                            @csrf
                            <input type="hidden" name="phrase" value="{{ $suggestion['phrase'] }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Öneriyi kaldır: :phrase', ['phrase' => $suggestion['phrase']]) }}">
                                <i class="ti ti-x"></i>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
