@props(['linkClass' => ''])
<span {{ $attributes }}>{{ config('app.name') }} <a href="{{ \App\Support\AppVersion::releaseUrl() }}" class="{{ $linkClass }}" target="_blank" rel="noopener" title="{{ __('Sürüm notları') }}">v{{ config('app.version') }}</a></span>
