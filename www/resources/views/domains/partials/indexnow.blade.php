@php
    $indexNow = app(\App\Services\IndexNow\IndexNowService::class);
    $indexNowKey = $domain->indexnow_key;
    $indexNowSubmissions = $domain->indexNowSubmissions()->limit(10)->get();
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-bolt me-1"></i>{{ __('IndexNow ile hızlı indeksleme') }}</h3>
            </div>
            <div class="card-body">
                <p class="text-secondary">
                    {{ __('Yeni ya da değişen sayfalarınızı Bing, Yandex, Seznam ve Naver\'e anında bildirin. Arama motorları sayfayı keşfetmek için taramayı beklemez. Google IndexNow\'u desteklemiyor.') }}
                </p>

                @if (!$canEdit)
                    <p class="text-secondary small mb-0"><i class="ti ti-lock me-1"></i>{{ __('Bu domaine salt okunur erişiminiz var; IndexNow ayarlarını yalnızca düzenleme yetkisi olanlar değiştirebilir.') }}</p>
                @elseif (!$indexNowKey)
                    <div class="border rounded-3 p-3">
                        <div class="fw-semibold mb-1">{{ __('1. Anahtar oluşturun') }}</div>
                        <p class="text-secondary small mb-2">{{ __('Bildirimlerin sizden geldiğini kanıtlamak için sitenize küçük bir anahtar dosyası koymanız gerekiyor.') }}</p>
                        <form method="post" action="{{ route('domains.indexnow.regenerate', $domain) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary"><i class="ti ti-key icon"></i> {{ __('Anahtar oluştur') }}</button>
                        </form>
                    </div>
                @else
                    <ol class="indexnow-steps">
                        <li>
                            <div class="fw-semibold">{{ __('Anahtar dosyasını indirin') }}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                <a href="{{ route('domains.indexnow.key-file', $domain) }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-download icon"></i> {{ $indexNowKey }}.txt</a>
                                <form method="post" action="{{ route('domains.indexnow.regenerate', $domain) }}" onsubmit="return confirm({{ json_encode(__('Yeni anahtar oluşturulursa eski dosya geçersiz olur. Devam edilsin mi?')) }});">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost-secondary btn-sm">{{ __('Yeni anahtar') }}</button>
                                </form>
                            </div>
                        </li>
                        <li>
                            <div class="fw-semibold">{{ __('Sitenizin köküne yükleyin') }}</div>
                            <div class="small text-secondary">{{ __('Dosya şu adresten açılabilmeli:') }}</div>
                            <code class="d-inline-block text-break">{{ $indexNow->keyFileUrl($domain) }}</code>
                        </li>
                        <li>
                            <div class="fw-semibold">{{ __('Doğrulayın') }}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                @if ($domain->indexnow_verified_at)
                                    <span class="badge bg-success-lt"><i class="ti ti-circle-check me-1"></i>{{ __('Doğrulandı: :when', ['when' => $domain->indexnow_verified_at->diffForHumans()]) }}</span>
                                @else
                                    <span class="badge bg-warning-lt">{{ __('Henüz doğrulanmadı') }}</span>
                                @endif
                                <form method="post" action="{{ route('domains.indexnow.verify', $domain) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="ti ti-shield-check icon"></i> {{ __('Dosyayı kontrol et') }}</button>
                                </form>
                            </div>
                        </li>
                    </ol>

                    <hr>

                    <div class="fw-semibold mb-2">{{ __('URL bildir') }}</div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <form method="post" action="{{ route('domains.indexnow.submit', $domain) }}">
                            @csrf
                            <input type="hidden" name="source" value="sitemap">
                            <button type="submit" class="btn btn-primary" @disabled($sitemapUrlCounts['active'] === 0)>
                                <i class="ti ti-sitemap icon"></i> {{ trans_choice('{0} Site haritasında sayfa yok|{1} Site haritasındaki 1 sayfayı bildir|[2,*] Site haritasındaki :count sayfayı bildir', $sitemapUrlCounts['active'], ['count' => number_format($sitemapUrlCounts['active'])]) }}
                            </button>
                        </form>
                    </div>
                    <form method="post" action="{{ route('domains.indexnow.submit', $domain) }}">
                        @csrf
                        <input type="hidden" name="source" value="manual">
                        <label class="form-label" for="indexnow-urls">{{ __('Ya da URL\'leri elle girin (her satıra bir tane)') }}</label>
                        <textarea id="indexnow-urls" name="urls" class="form-control font-monospace" rows="4" placeholder="{{ $domain->rootUrl() }}yeni-sayfa">{{ old('urls') }}</textarea>
                        <button type="submit" class="btn btn-outline-primary mt-2"><i class="ti ti-send icon"></i> {{ __('Bildir') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Son bildirimler') }}</h3>
            </div>
            @if ($indexNowSubmissions->isEmpty())
                <div class="card-body text-secondary">{{ __('Henüz bildirim yapılmadı.') }}</div>
            @else
                <div class="list-group list-group-flush">
                    @foreach ($indexNowSubmissions as $submission)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ti {{ $submission->ok ? 'ti-circle-check text-success' : 'ti-circle-x text-danger' }}"></i>
                                <span class="fw-medium">{{ trans_choice('{0} URL yok|{1} 1 URL|[2,*] :count URL', $submission->url_count, ['count' => number_format($submission->url_count)]) }}</span>
                                <span class="badge bg-secondary-lt">{{ ['sitemap' => __('site haritası'), 'manual' => __('elle'), 'mcp' => 'MCP'][$submission->source] ?? $submission->source }}</span>
                                <span class="small text-secondary ms-auto">{{ $submission->created_at->diffForHumans() }}</span>
                            </div>
                            @if ($submission->message)
                                <div class="small text-secondary mt-1">{{ $submission->message }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
