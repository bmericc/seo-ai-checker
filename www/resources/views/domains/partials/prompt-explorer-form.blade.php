@php
    $explorerProviders = app(\App\Services\Llm\PromptExplorer::class)->availableProviders($domain);
    $prefill = $prefill ?? old('prompt');
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-message-search me-1"></i>{{ __('Prompt Explorer') }}</h3>
    </div>
    <div class="card-body">
        <p class="text-secondary">
            {{ __('Bir soruyu tüm yapay zekâ asistanlarına aynı anda sorun, cevapları yan yana karşılaştırın. :domain adının geçip geçmediğini ve hangi sitelerin önerildiğini görün.', ['domain' => $domain->domain]) }}
        </p>

        @if ($explorerProviders === [])
            <div class="text-secondary">{{ __('Kullanmak için aşağıdan en az bir sağlayıcının API anahtarını girin.') }}</div>
        @else
            <form method="post" action="{{ route('domains.prompts.store', $domain) }}">
                @csrf
                <textarea name="prompt" class="form-control" rows="3" maxlength="{{ \App\Services\Llm\PromptExplorer::MAX_PROMPT_LENGTH }}" required placeholder="{{ __('Örnek: İstanbul\'da en iyi diş kliniği hangisi?') }}">{{ $prefill }}</textarea>
                <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                    @foreach ($explorerProviders as $provider)
                        <label class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" name="providers[]" value="{{ $provider }}" checked>
                            <span class="form-check-label">{{ \App\Models\DomainLlmApiKey::PROVIDER_LABELS[$provider] ?? $provider }}</span>
                        </label>
                    @endforeach
                    <button type="submit" class="btn btn-primary ms-auto"><i class="ti ti-send icon"></i> {{ __('Sor') }}</button>
                </div>
            </form>

            @if ($domain->keywords->isNotEmpty())
                <details class="mt-3" id="prompt-suggester">
                    <summary class="text-primary" style="cursor: pointer"><i class="ti ti-wand me-1"></i>{{ __('Soru öner') }}</summary>
                    <div class="mt-2">
                        <p class="text-secondary small mb-2">
                            {{ __('Seçtiğiniz anahtar kelimelerden, gerçek bir müşterinin yapay zekâ asistanına soracağı marka içermeyen soru varyasyonları üretir. Bir sağlayıcıya tek istek gönderilir.') }}
                        </p>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label small mb-1" for="suggest-keywords">{{ __('Anahtar kelimeler') }}</label>
                                <select id="suggest-keywords" class="form-select" multiple size="{{ min(5, max(2, $domain->keywords->count())) }}">
                                    @foreach ($domain->keywords as $suggestKeyword)
                                        <option value="{{ $suggestKeyword->id }}" @selected($loop->first)>{{ $suggestKeyword->keyword }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1" for="suggest-intent">{{ __('Arama niyeti') }}</label>
                                <select id="suggest-intent" class="form-select">
                                    <option value="">{{ __('Karışık') }}</option>
                                    <option value="informational">{{ __('Bilgi arayan') }}</option>
                                    <option value="commercial">{{ __('Seçenek karşılaştıran') }}</option>
                                    <option value="transactional">{{ __('Satın almaya hazır') }}</option>
                                    <option value="local">{{ __('Yerel') }}</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-primary w-100" id="suggest-button">{{ __('Öner') }}</button>
                            </div>
                        </div>
                        <div id="suggest-status" class="small mt-2"></div>
                        <div id="suggest-results" class="list-group list-group-flush mt-1"></div>
                    </div>
                </details>
                <script>
                    (function () {
                        var button = document.getElementById('suggest-button');
                        var status = document.getElementById('suggest-status');
                        var results = document.getElementById('suggest-results');
                        var textarea = document.querySelector('textarea[name="prompt"]');
                        var labels = {
                            loading: @json(__('Sorular hazırlanıyor…')),
                            from: @json(__(':provider önerdi, kullanmak için birine tıklayın.')),
                            failed: @json(__('Öneri alınamadı.')),
                        };

                        button.addEventListener('click', function () {
                            var ids = Array.from(document.getElementById('suggest-keywords').selectedOptions).map(function (o) { return o.value; });
                            button.disabled = true;
                            status.className = 'small mt-2 text-secondary';
                            status.textContent = labels.loading;
                            results.innerHTML = '';

                            fetch(@json(route('domains.prompts.suggest', $domain)), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': @json(csrf_token()),
                                },
                                body: JSON.stringify({ keyword_ids: ids, intent: document.getElementById('suggest-intent').value || null }),
                            })
                                .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
                                .then(function (res) {
                                    if (!res.ok) {
                                        throw new Error(res.body.message || labels.failed);
                                    }
                                    status.textContent = labels.from.replace(':provider', res.body.provider);
                                    res.body.suggestions.forEach(function (text) {
                                        var item = document.createElement('button');
                                        item.type = 'button';
                                        item.className = 'list-group-item list-group-item-action';
                                        item.textContent = text;
                                        item.addEventListener('click', function () {
                                            textarea.value = text;
                                            textarea.focus();
                                        });
                                        results.appendChild(item);
                                    });
                                })
                                .catch(function (error) {
                                    status.className = 'small mt-2 text-danger';
                                    status.textContent = error.message || labels.failed;
                                })
                                .finally(function () { button.disabled = false; });
                        });
                    })();
                </script>
            @endif
        @endif

    </div>
</div>
