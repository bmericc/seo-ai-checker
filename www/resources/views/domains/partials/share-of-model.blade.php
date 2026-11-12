{{-- Yapay zeka cevaplarinda domain payi (bkz. App\Services\Analytics\LlmShareOfModel). --}}
@php
    $cellClass = function (int $rate): string {
        return match (true) {
            $rate >= 50 => 'bg-success-lt',
            $rate >= 20 => 'bg-azure-lt',
            $rate > 0 => 'bg-secondary-lt',
            default => 'text-secondary',
        };
    };
@endphp

<div class="card mb-4">
    <div class="card-header">
        <div>
            <h3 class="card-title"><i class="ti ti-sparkles me-1"></i>{{ __('Yapay zekâ cevaplarında pay (Share of Model)') }}</h3>
            <div class="text-secondary small">
                {{ __('Son 90 gündeki AI kontrollerinde her asistanın cevaplarının yüzde kaçında hangi domainin geçtiği. Yalnızca domain adıyla anılan siteler sayılır.') }}
            </div>
        </div>
    </div>

    @if (empty($shareOfModel['rows']))
        <div class="card-body">
            <p class="text-secondary mb-0">{{ __('Henüz AI cevabı yok. AI görünürlük kontrolü, Prompt Explorer ya da MCP check_ai_answer ile cevap toplandıkça matris dolar.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table card-table table-vcenter text-nowrap">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th class="text-center">{{ __('Tümü') }} <span class="text-secondary fw-normal">({{ $shareOfModel['answers'] }})</span></th>
                        @foreach ($shareOfModel['providers'] as $provider)
                            <th class="text-center">{{ \App\Support\AiAssistant::label($provider) }} <span class="text-secondary fw-normal">({{ $shareOfModel['answered'][$provider] }})</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shareOfModel['rows'] as $row)
                        <tr @class(['table-active' => $row['own']])>
                            <td class="fw-medium">
                                {{ $row['domain'] }}
                                @if ($row['own'])
                                    <span class="badge bg-primary-lt ms-1">{{ __('Siz') }}</span>
                                @endif
                            </td>
                            <td class="text-center"><span class="badge {{ $cellClass($row['rate']) }}">{{ $row['rate'] }}%</span></td>
                            @foreach ($shareOfModel['providers'] as $provider)
                                @php $rate = $row['by_provider'][$provider] ?? 0; @endphp
                                <td class="text-center"><span class="badge {{ $cellClass($rate) }}">{{ $rate }}%</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer text-secondary small">
            {{ __('Parantez içindeki sayı, o asistandan alınan hatasız cevap adedidir.') }}
        </div>
    @endif
</div>
