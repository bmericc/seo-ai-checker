@extends('layout')

@section('title', __('Ayarlar'))
@section('page-title', __('Ayarlar'))

@section('content')
    @php $current = app()->getLocale(); @endphp
    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <form method="post" action="{{ route('locale.update') }}" class="card">
                @csrf
                <div class="card-body">
                    <div class="fw-semibold mb-1">{{ __('Arayüz dili') }}</div>
                    <div class="text-secondary small mb-3">{{ __('Panel, e-postalar ve raporlar bu dilde gösterilir. İlk girişte tarayıcınızın diline göre seçilir.') }}</div>
                    <div class="form-selectgroup">
                        @foreach (['tr' => 'Türkçe', 'en' => 'English'] as $code => $label)
                            <label class="form-selectgroup-item">
                                <input type="radio" name="locale" value="{{ $code }}" class="form-selectgroup-input" @checked($current === $code)>
                                <span class="form-selectgroup-label" lang="{{ $code }}">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary">{{ __('Kaydet') }}</button>
                </div>
            </form>

            @if ($competitorBlacklist !== null)
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('Rakip kara listesi') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="text-secondary small mb-3">{{ __('Bu listedeki domainler (ve alt domainleri) hiçbir sitenin SERP rakip keşfinde ve yapay zekâ payı tablosunda çıkmaz, rakip olarak da eklenemez. Tüm kullanıcılar için geçerlidir.') }}</div>
                        <form method="post" action="{{ route('admin.competitor-blacklist.store') }}" class="mb-3">
                            @csrf
                            <textarea name="domains" rows="2" class="form-control mb-2" placeholder="facebook.com, instagram.com" aria-label="{{ __('Kara listeye eklenecek domainler') }}" required></textarea>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="text-secondary small">{{ __('Birden çok domaini virgül, boşluk ya da satır sonuyla ayırabilirsiniz.') }}</span>
                                <button type="submit" class="btn btn-primary"><i class="ti ti-plus icon"></i> {{ __('Ekle') }}</button>
                            </div>
                        </form>
                        @if ($competitorBlacklist->isEmpty())
                            <p class="text-secondary mb-0">{{ __('Kara liste boş.') }}</p>
                        @else
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($competitorBlacklist as $entry)
                                    <form method="post" action="{{ route('admin.competitor-blacklist.destroy', $entry) }}">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Kara listeden çıkar') }}">
                                            {{ $entry->domain }} <i class="ti ti-x ms-1"></i>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
