{{--
    AI Overview istikrari (bkz. App\Services\Analytics\AiOverviewVolatility).
    $aioStability: forKeywords() ciktisi (domain sayfasi) ya da
    $aioKeywordStability: forChecks() ciktisi (anahtar kelime sayfasi).
--}}
@php
    $levelBadges = [
        'stable' => ['bg-success-lt', __('Stabil')],
        'medium' => ['bg-warning-lt', __('Orta')],
        'high' => ['bg-danger-lt', __('Dalgalı')],
    ];
    $pct = fn ($value) => $value === null ? '–' : $value . '%';
    $window = \App\Services\Analytics\AiOverviewVolatility::WINDOW;
@endphp

<div class="card mb-4">
    <div class="card-header">
        <div>
            <h3 class="card-title"><i class="ti ti-wave-sine me-1"></i>{{ __('AI Overview istikrarı') }}</h3>
            <div class="text-secondary small">
                {{ __('Son :count engellenmemiş kontrol: AI Overview ne sıklıkla çıkıyor, siteniz kaynaklarda ne kadar kalıcı ve durum kontrolden kontrole ne kadar değişiyor.', ['count' => $window]) }}
            </div>
        </div>
    </div>

    @if (isset($aioKeywordStability))
        <div class="card-body">
            @if ($aioKeywordStability['checks'] === 0)
                <p class="text-secondary mb-0">{{ __('Henüz engellenmemiş bir SERP kontrolü yok.') }}</p>
            @else
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('AI Overview çıkma oranı') }}</div>
                        <div class="h2 mb-0">{{ $pct($aioKeywordStability['aio_rate']) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Kaynak olma oranı') }}</div>
                        <div class="h2 mb-0">{{ $pct($aioKeywordStability['citation_rate']) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Değişim oranı') }}</div>
                        <div class="h2 mb-0">
                            {{ $pct($aioKeywordStability['change_rate']) }}
                            @if ($aioKeywordStability['level'])
                                <span class="badge {{ $levelBadges[$aioKeywordStability['level']][0] }} fs-5 align-middle">{{ $levelBadges[$aioKeywordStability['level']][1] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Ölçülen kontrol') }}</div>
                        <div class="h2 mb-0">{{ $aioKeywordStability['checks'] }}</div>
                    </div>
                </div>
            @endif
        </div>
    @else
        @php $summary = $aioStability['summary']; @endphp
        @if ($summary['measured_keywords'] === 0)
            <div class="card-body">
                <p class="text-secondary mb-0">{{ __('Henüz engellenmemiş bir SERP kontrolü yok.') }}</p>
            </div>
        @else
            <div class="card-body border-bottom">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Ortalama AI Overview çıkma oranı') }}</div>
                        <div class="h2 mb-0">{{ $pct($summary['aio_rate']) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Ortalama kaynak olma oranı') }}</div>
                        <div class="h2 mb-0">{{ $pct($summary['citation_rate']) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Dalgalı anahtar kelime') }}</div>
                        <div class="h2 mb-0">{{ $summary['volatile_keywords'] }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">{{ __('Ölçülen anahtar kelime') }}</div>
                        <div class="h2 mb-0">{{ $summary['measured_keywords'] }}</div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>{{ __('Anahtar kelime') }}</th>
                            <th>{{ __('AI Overview çıkma') }}</th>
                            <th>{{ __('Kaynak olma') }}</th>
                            <th>{{ __('Değişim') }}</th>
                            <th>{{ __('Kontrol') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($aioStability['keywords'])->where('checks', '>', 0)->sortByDesc('change_rate') as $row)
                            <tr>
                                <td><a href="{{ route('keywords.show', $row['keyword_id']) }}">{{ $row['keyword'] }}</a></td>
                                <td>{{ $pct($row['aio_rate']) }}</td>
                                <td>{{ $pct($row['citation_rate']) }}</td>
                                <td>
                                    {{ $pct($row['change_rate']) }}
                                    @if ($row['level'])
                                        <span class="badge {{ $levelBadges[$row['level']][0] }} ms-1">{{ $levelBadges[$row['level']][1] }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary">{{ $row['checks'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
