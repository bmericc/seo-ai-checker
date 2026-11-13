{{-- Backlink sekmesi: CrawlGraph / Common Crawl ozeti (bkz. SyncCrawlGraphBacklinks). Domain listeleri
     ust taraftaki birlesik tabloda (BacklinkMerger). --}}
@php $cgFmt = fn ($v) => $v === null ? '—' : number_format((int) $v, 0, ',', '.'); @endphp

<div class="card mt-3">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ __('Common Crawl (CrawlGraph)') }}</h3>
            <div class="text-secondary small">
                {{ __('Ücretsiz, açık arşiv verisi: canlı değil, üç ayda bir yenilenir. Domain\'ler yukarıdaki birleşik listede CC etiketiyle görünür.') }}
            </div>
        </div>
    </div>

    @if (!$crawlGraphConfigured)
        <div class="card-body text-secondary">{{ __('CRAWLGRAPH_API_KEY tanımlı değil.') }}</div>
    @elseif ($crawlGraph === null)
        <div class="card-body text-secondary">
            {{ __('Henüz veri yok. Her gün yeni arşiv kontrol edilir; ücretsiz kota ayda 15 domain sorgusu olduğu için sıra bu domaine gelince dolar.') }}
        </div>
    @else
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="subheader">{{ __('Arşiv') }}</div>
                    <div class="fw-semibold">{{ $crawlGraph->release_label ?? $crawlGraph->release_id }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="subheader">{{ __('Link veren domain') }}</div>
                    <div class="h2 mb-0">{{ $cgFmt($crawlGraph->total_linking_domains) }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="subheader">{{ __('Authority') }}</div>
                    <div class="h2 mb-0">{{ $crawlGraph->authority ?? '—' }}<span class="fs-5 text-secondary">/100</span></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="subheader">{{ __('Sıra (PageRank)') }}</div>
                    <div class="h2 mb-0">{{ $cgFmt($crawlGraph->rank) }}</div>
                </div>
            </div>
            @if ($crawlGraph->added_domains === null)
                <p class="text-secondary small mt-3 mb-0">{{ __('Yeni / görünmeyen domain karşılaştırması bir sonraki arşivle başlayacak.') }}</p>
            @endif
        </div>

    @endif
</div>
