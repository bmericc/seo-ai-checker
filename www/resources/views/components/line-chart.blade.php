@props([
    'labels' => [],
    'series' => [],
    'min' => null,
    'max' => null,
    'height' => 160,
    'valueSuffix' => '',
    'invertY' => false,
])

@php
    $format = function (float $v) {
        $s = number_format($v, 1, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    };

    $allValues = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null)->values();
    $hasData = $allValues->isNotEmpty() && count($labels) > 1;
@endphp

@if (!$hasData)
    <p class="text-secondary small mb-0">{{ __('Henüz yeterli veri yok (en az 2 farklı günde kontrol gerekir).') }}</p>
@else
    @php
        $dataMin = $allValues->min();
        $dataMax = $allValues->max();
        $chartMin = $min ?? $dataMin;
        $chartMax = $max ?? $dataMax;
        if ($chartMax <= $chartMin) {
            $chartMax = $chartMin + 1;
        }

        $width = 600;
        $paddingLeft = 34;
        $paddingRight = 8;
        $paddingTop = 10;
        $paddingBottom = 20;
        $plotWidth = $width - $paddingLeft - $paddingRight;
        $plotHeight = $height - $paddingTop - $paddingBottom;
        $count = count($labels);

        $xFor = fn (int $i) => $paddingLeft + ($count > 1 ? ($i / ($count - 1)) * $plotWidth : $plotWidth / 2);
        $yFor = function (float $v) use ($chartMin, $chartMax, $paddingTop, $plotHeight, $invertY) {
            $ratio = max(0, min(1, ($v - $chartMin) / ($chartMax - $chartMin)));
            return $invertY
                ? $paddingTop + $ratio * $plotHeight
                : $paddingTop + (1 - $ratio) * $plotHeight;
        };

        $paths = [];
        foreach ($series as $s) {
            $segments = [];
            $current = [];
            foreach ($s['values'] as $i => $v) {
                if ($v === null) {
                    if (count($current) > 0) {
                        $segments[] = $current;
                        $current = [];
                    }
                    continue;
                }
                $current[] = [$xFor($i), $yFor((float) $v)];
            }
            if (count($current) > 0) {
                $segments[] = $current;
            }
            $paths[] = ['label' => $s['label'], 'color' => $s['color'] ?? '#206bc4', 'segments' => $segments, 'values' => $s['values']];
        }

        $gridValues = [$chartMin, ($chartMin + $chartMax) / 2, $chartMax];
        $xLabelIndexes = $count <= 6 ? range(0, $count - 1) : [0, (int) round(($count - 1) / 2), $count - 1];
    @endphp

    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" style="width: 100%; height: {{ $height }}px;" role="img" aria-label="{{ collect($series)->pluck('label')->implode(', ') }}">
        @foreach ($gridValues as $g)
            @php $gy = $yFor((float) $g); @endphp
            <line x1="{{ $paddingLeft }}" y1="{{ $gy }}" x2="{{ $width - $paddingRight }}" y2="{{ $gy }}" stroke="currentColor" stroke-opacity="0.12" stroke-width="1"></line>
            <text x="0" y="{{ $gy + 3 }}" font-size="9" fill="currentColor" fill-opacity="0.6">{{ $format((float) $g) }}{{ $valueSuffix }}</text>
        @endforeach

        @foreach ($xLabelIndexes as $i)
            <text
                x="{{ $xFor($i) }}"
                y="{{ $height - 4 }}"
                font-size="9"
                fill="currentColor"
                fill-opacity="0.6"
                text-anchor="{{ $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') }}"
            >{{ $labels[$i] }}</text>
        @endforeach

        @foreach ($paths as $p)
            @foreach ($p['segments'] as $segment)
                <polyline
                    points="{{ collect($segment)->map(fn ($pt) => $pt[0] . ',' . $pt[1])->implode(' ') }}"
                    fill="none"
                    stroke="{{ $p['color'] }}"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                ></polyline>
            @endforeach
            @foreach ($p['values'] as $i => $v)
                @continue($v === null)
                <circle cx="{{ $xFor($i) }}" cy="{{ $yFor((float) $v) }}" r="2.5" fill="{{ $p['color'] }}">
                    <title>{{ $p['label'] }}: {{ $format((float) $v) }}{{ $valueSuffix }} ({{ $labels[$i] }})</title>
                </circle>
            @endforeach
        @endforeach
    </svg>

    @if (count($paths) > 1)
        <div class="d-flex flex-wrap gap-3 mt-1">
            @foreach ($paths as $p)
                <div class="d-flex align-items-center gap-1 small text-secondary">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $p['color'] }};"></span>
                    {{ $p['label'] }}
                </div>
            @endforeach
        </div>
    @endif
@endif
