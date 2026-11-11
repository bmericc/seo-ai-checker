@php
    $historyKeyword = $historyKeyword ?? null;
    $historyItems = ($historyKeyword ? $historyKeyword->aiChecks() : $domain->promptExplorations())->with('keyword')->limit($limit ?? 20)->get();
@endphp

@if ($historyItems->isNotEmpty() || !($hideEmpty ?? false))

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-history me-1"></i>{{ __('Yapay zekâ kontrol geçmişi') }}</h3>
    </div>
    @if ($historyItems->isEmpty())
        <div class="card-body text-secondary">
            {{ __('Henüz kayıt yok. AI görünürlük kontrolü açıksa her anahtar kelime kontrolünde sonuçlar buraya düşer; MCP bağlantısındaki asistanınıza da check_ai_answer ile kontrol ettirebilirsiniz.') }}
        </div>
    @else
        <div class="list-group list-group-flush">
            @foreach ($historyItems as $item)
                <a href="{{ route('domains.prompts.show', [$domain, $item]) }}" class="list-group-item list-group-item-action d-flex flex-wrap align-items-center gap-2">
                    <span class="text-truncate flex-fill" style="min-width: 12rem">{{ $item->prompt }}</span>
                    @if ($item->keyword && !$historyKeyword)
                        <span class="badge bg-azure-lt"><i class="ti ti-key me-1"></i>{{ $item->keyword->keyword }}</span>
                    @endif
                    @if ($item->isAutomatic())
                        <span class="badge bg-teal-lt" title="{{ __('Anahtar kelime kontrolü sırasında otomatik soruldu') }}"><i class="ti ti-refresh me-1"></i>{{ __('Otomatik') }}</span>
                    @endif
                    @if (!$item->isComplete())
                        <span class="badge bg-blue-lt">{{ __('Çalışıyor') }}</span>
                    @endif
                    @foreach ($item->providers ?? [] as $provider)
                        @php $result = $item->resultFor($provider); @endphp
                        @if ($result !== null)
                            <span class="badge {{ ($result['error'] ?? null) ? 'bg-danger-lt' : (($result['present'] ?? false) ? 'bg-success-lt' : 'bg-secondary-lt') }}">
                                <i class="ti {{ ($result['present'] ?? false) ? 'ti-check' : 'ti-x' }} me-1"></i>{{ \App\Support\AiAssistant::label($provider) }}@if ($result['model'] ?? null)<span class="fw-normal ms-1 opacity-75">{{ $result['model'] }}</span>@endif
                            </span>
                            @if (($result['source'] ?? null) === 'manual')
                                <span class="badge bg-purple-lt">{{ __('Manuel (MCP)') }}</span>
                            @endif
                        @endif
                    @endforeach
                    <span class="small text-secondary" title="{{ $item->created_at->toDateTimeString() }}">{{ $item->created_at->diffForHumans() }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endif
