{{-- Misafir sayfalari icin dil degistirici: aktif olmayan dili gosterir. --}}
@php
    $other = app()->getLocale() === 'tr' ? 'en' : 'tr';
    $labels = ['tr' => 'Türkçe', 'en' => 'English'];
@endphp
<form method="post" action="{{ route('locale.update') }}" class="d-inline m-0">
    @csrf
    <input type="hidden" name="locale" value="{{ $other }}">
    <button type="submit" lang="{{ $other }}" {{ $attributes->merge(['class' => 'locale-switch']) }}>
        <i class="ti ti-language" aria-hidden="true"></i> {{ $labels[$other] }}
    </button>
</form>
