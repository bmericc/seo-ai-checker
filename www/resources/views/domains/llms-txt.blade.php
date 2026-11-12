@extends('layout')

@section('title', 'llms.txt — ' . $domain->domain)

@section('page-pretitle')
    <a href="{{ route('domains.show', $domain) }}" class="text-secondary">&larr; {{ $domain->domain }}</a>
@endsection
@section('page-title', __('llms.txt taslağı'))
@section('page-actions')
    @if ($content !== null)
        <a href="{{ route('domains.llms-txt.download', $domain) }}" class="btn btn-primary">
            <i class="ti ti-download icon"></i> {{ __('llms.txt indir') }}
        </a>
    @endif
@endsection

@section('content')
    @if ($content === null)
        <div class="card">
            <div class="empty">
                <div class="empty-icon"><i class="ti ti-file-text fs-1"></i></div>
                <p class="empty-title">{{ __('Sitemap\'ten keşfedilmiş sayfa yok') }}</p>
                <p class="empty-subtitle text-secondary">{{ __('Taslak, site kontrolünün sitemap.xml\'den bulduğu sayfalardan üretilir. Önce site kontrolünü çalıştırın.') }}</p>
            </div>
        </div>
    @else
        @if (($domain->fact?->llms_txt['found'] ?? false))
            <div class="alert alert-info">
                {{ __('Sitenizde zaten bir llms.txt var. Bu taslak onun yerine geçmek zorunda değil; eksik sayfaları görmek için karşılaştırabilirsiniz.') }}
                <a href="{{ $domain->fact->llms_txt['url'] }}" target="_blank" rel="noopener">{{ $domain->fact->llms_txt['url'] }}</a>
                @include('domains.partials.llms-txt-validation', ['validation' => $domain->fact->llms_txt['validation'] ?? null])
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ __(':count sayfadan üretildi', ['count' => $pageCount]) }}</h3>
                    <div class="text-secondary small">
                        {{ __('Başlık ve açıklamalar sayfa raporunda analiz edilmiş :scanned sayfadan alındı; diğerlerinde URL\'den türetildi. Yayınlamadan önce gözden geçirip sitenizin köküne /llms.txt olarak yükleyin.', ['scanned' => $scannedCount]) }}
                        @if ($scannedCount < $pageCount)
                            <a href="{{ route('domains.lighthouse-report', $domain) }}">{{ __('Sayfa raporuna git') }}</a>
                        @endif
                    </div>
                </div>
                <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-target="#llms-txt-content">
                        <i class="ti ti-copy icon"></i> {{ __('Kopyala') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <textarea id="llms-txt-content" class="form-control font-monospace" rows="28" spellcheck="false">{{ $content }}</textarea>
            </div>
        </div>
<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.querySelector(button.dataset.copyTarget);
            navigator.clipboard.writeText(target.value).then(function () {
                var original = button.innerHTML;
                button.textContent = @json(__('Kopyalandı'));
                setTimeout(function () { button.innerHTML = original; }, 1500);
            });
        });
    });
</script>
    @endif
@endsection
