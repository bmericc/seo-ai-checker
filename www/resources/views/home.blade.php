@extends('layout')

@section('title', config('app.name'))

@section('content')
    <div class="home-hero">
        <h1>{{ config('app.name') }}</h1>
        <p class="lead">
            Google arama sonuçlarında sıralama takibi, Google
            <strong>AI Overview</strong> kutusunda görünürlük kontrolü, temel
            <strong>on-page SEO</strong> analizi ve <strong>Lighthouse</strong>
            (Google PageSpeed Insights) skorlarını bir araya getiren bir
            panel.
        </p>

        <ul class="home-features">
            <li>
                <strong>SERP sıralaması</strong> — Google'ın ilk ~20 organik
                sonucunu çeker, takip edilen domainin kaçıncı sırada
                çıktığını raporlar.
            </li>
            <li>
                <strong>AI Overview kontrolü</strong> — sonuç sayfasında bir
                AI Overview kutusu olup olmadığını, varsa hangi domainlerin
                kaynak olarak gösterildiğini raporlar.
            </li>
            <li>
                <strong>On-page SEO analizi</strong> — title/meta description
                uzunluğu, başlık yapısı, kelime yoğunluğu, eksik `alt`
                etiketleri, iç/dış link sayısı gibi temel kontroller.
            </li>
            <li>
                <strong>Lighthouse skorları</strong> — Google PageSpeed
                Insights üzerinden performans, SEO, erişilebilirlik ve
                best-practices puanları.
            </li>
        </ul>

        <p class="muted">
            Bu panel herkese açık değildir; yalnızca site sahibi tarafından
            önceden izin verilmiş Google hesaplarıyla giriş yapılabilir.
            Erişiminiz varsa aşağıdan giriş yapabilirsiniz.
        </p>

        <a href="{{ route('google.redirect') }}" class="btn-google">Google ile giriş yap</a>
    </div>
@endsection
