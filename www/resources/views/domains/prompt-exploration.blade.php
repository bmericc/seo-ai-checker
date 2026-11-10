@extends('layout')

@section('title', __('Prompt Explorer') . ' — ' . $domain->domain)

@section('page-pretitle')
    <a href="{{ route('domains.show', $domain) }}#tab-ai" class="text-secondary">&larr; {{ $domain->domain }}</a>
@endsection
@section('page-title', __('Prompt Explorer'))

@if (!$exploration->isComplete())
    @push('head')
        <meta http-equiv="refresh" content="4">
    @endpush
@endif

@section('content')
    @php
        $summary = $exploration->mentionSummary();
        $sites = $exploration->mentionedSites();
        $labels = \App\Models\DomainLlmApiKey::PROVIDER_LABELS;
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <div class="subheader mb-1">{{ __('Soru') }}</div>
            <div class="h3 mb-2">{{ $exploration->prompt }}</div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($exploration->isComplete())
                    <span class="badge {{ $summary['mentioned'] > 0 ? 'bg-success-lt' : 'bg-secondary-lt' }} fs-5">
                        {{ __(':domain, :answered asistandan :mentioned tanesinde geçiyor', ['domain' => $domain->domain] + $summary) }}
                    </span>
                @else
                    <span class="badge bg-blue-lt fs-5"><span class="spinner-border spinner-border-sm me-1"></span>{{ __('Cevaplar geliyor… (:done/:total)', ['done' => count($exploration->results ?? []), 'total' => count($exploration->providers)]) }}</span>
                @endif
                <span class="small text-secondary ms-auto">{{ $exploration->created_at->diffForHumans() }}</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3 prompt-results">
        @foreach ($exploration->providers as $provider)
            @php $result = $exploration->resultFor($provider); @endphp
            <div class="col-12 col-md-6 col-xxl-{{ count($exploration->providers) >= 4 ? 3 : (count($exploration->providers) === 3 ? 4 : 6) }}">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ $labels[$provider] ?? $provider }}
                            @if ($result['model'] ?? null)
                                <span class="text-secondary fw-normal small ms-1">{{ $result['model'] }}</span>
                            @endif
                        </h3>
                        <div class="card-actions">
                            @if ($result === null)
                                <span class="spinner-border spinner-border-sm text-secondary"></span>
                            @elseif ($result['error'] ?? null)
                                <span class="badge bg-danger-lt">{{ __('Hata') }}</span>
                            @elseif ($result['present'])
                                <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i>{{ __('Anıldı') }}</span>
                            @else
                                <span class="badge bg-secondary-lt">{{ __('Anılmadı') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body prompt-answer">
                        @if ($result === null)
                            <div class="placeholder-glow">
                                <span class="placeholder col-10"></span>
                                <span class="placeholder col-8"></span>
                                <span class="placeholder col-11"></span>
                            </div>
                        @elseif ($result['error'] ?? null)
                            <div class="text-danger small">{{ $result['error'] }}</div>
                        @else
                            {!! \App\Support\LlmAnswerFormatter::toHtml((string) $result['response'], $domain->domain) !!}
                            @if (!empty($result['citations']))
                                <div class="subheader mt-3 mb-1">{{ __('Kaynaklar') }}</div>
                                <ol class="small ps-3 mb-0">
                                    @foreach ($result['citations'] as $url)
                                        <li class="text-break"><a href="{{ $url }}" target="_blank" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($url, 80) }}</a></li>
                                    @endforeach
                                </ol>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">{{ __('Cevaplarda anılan diğer siteler') }}</h3></div>
                @if ($sites === [])
                    <div class="card-body text-secondary">{{ $exploration->isComplete() ? __('Cevaplarda başka site adı geçmiyor.') : __('Cevaplar bekleniyor.') }}</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($sites as $host => $count)
                            <div class="list-group-item d-flex align-items-center">
                                <span class="text-truncate">{{ $host }}</span>
                                <span class="badge bg-secondary-lt ms-auto">{{ trans_choice('{1} 1 asistan|[2,*] :count asistan', $count, ['count' => $count]) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="col-12 col-lg-6">
            @include('domains.partials.prompt-explorer-form', ['prefill' => $exploration->prompt])
        </div>
    </div>
@endsection
