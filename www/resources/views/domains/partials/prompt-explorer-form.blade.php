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
        @endif

    </div>
</div>
