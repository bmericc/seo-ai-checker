@props([
    'labels' => [],
    'series' => [],
    'min' => null,
    'max' => null,
    'height' => 160,
    'valueSuffix' => '',
    'invertY' => false,
    'decimals' => 1,
])

@php
    $allValues = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null)->values();
    $hasData = $allValues->isNotEmpty();

    // Grafik ApexCharts ile tarayicida cizilir (bkz. layout.blade.php,
    // [data-apex-line]) - giris animasyonu, hover imleci ve ipucu icin.
    // Ayar JSON olarak data attribute'una gomulur; JS yoksa yerine asagidaki
    // <noscript> ozet tablosu gorunur.
    $config = [
        'categories' => array_values($labels),
        'series' => collect($series)->map(fn ($s) => [
            'name' => $s['label'],
            'data' => collect($s['values'])->map(fn ($v) => $v === null ? null : round((float) $v, max(0, (int) $decimals)))->values()->all(),
        ])->values()->all(),
        'colors' => collect($series)->map(fn ($s) => $s['color'] ?? '#206bc4')->values()->all(),
        'min' => $min,
        'max' => $max,
        'height' => (int) $height + (count($series) > 1 ? 28 : 0),
        'suffix' => $valueSuffix,
        'invertY' => (bool) $invertY,
        'decimals' => max(0, (int) $decimals),
    ];
@endphp

@if (!$hasData)
    <p class="text-secondary small mb-0">{{ __('Henüz veri yok.') }}</p>
@else
    <div class="apex-line"
         style="min-height: {{ $config['height'] }}px"
         data-apex-line="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}"
         role="img"
         aria-label="{{ collect($series)->pluck('label')->implode(', ') }}"></div>
    <noscript>
        <p class="small text-secondary mb-0">
            @foreach ($series as $s)
                {{ $s['label'] }}: {{ collect($s['values'])->filter(fn ($v) => $v !== null)->last() }}{{ $valueSuffix }}@if (!$loop->last) · @endif
            @endforeach
        </p>
    </noscript>
@endif
