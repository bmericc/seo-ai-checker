@props([
    'score' => null,
    'tone' => 'secondary',
    'size' => 64,
    'stroke' => 7,
    'showMax' => false,
])

@php
    $colors = [
        'success' => 'var(--tblr-success)',
        'warning' => 'var(--tblr-warning)',
        'danger' => 'var(--tblr-danger)',
        'secondary' => 'var(--tblr-secondary)',
    ];
    $color = $colors[$tone] ?? $colors['secondary'];
    $radius = 50 - $stroke / 2 - 1;
@endphp

<div {{ $attributes->class(['score-ring', 'is-pending' => $score === null]) }}
     style="--size: {{ $size }}px; --value: {{ (int) ($score ?? 0) }};"
     role="img"
     aria-label="{{ $score === null ? __('Henüz skor yok') : __('Sağlık skoru :score / 100', ['score' => $score]) }}">
    <svg viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring-track" cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $stroke }}"></circle>
        @if ($score !== null)
            <circle class="ring-value" cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}" stroke-linecap="round" pathLength="100"></circle>
        @endif
    </svg>
    <div class="ring-label" aria-hidden="true">
        @if ($score === null)
            <i class="ti ti-loader-2 text-secondary" style="font-size: calc(var(--size) * .3)"></i>
        @else
            <span data-count-to="{{ $score }}">0</span>
            @if ($showMax)
                <small>/ 100</small>
            @endif
        @endif
    </div>
</div>
