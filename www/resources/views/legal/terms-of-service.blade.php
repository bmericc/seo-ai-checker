@extends('layout')

@section('title', 'Kullanım Koşulları')
@section('page-title', 'Kullanım Koşulları')

@section('content')
    <div class="card">
    <div class="card-body legal">
        <p class="text-secondary updated-at">Son güncelleme: 16 Temmuz 2026</p>

        <p>
            Bu Kullanım Koşulları, <strong>{{ config('app.name') }}</strong>
            panelinin ("hizmet") kullanımını düzenler. Panele erişerek veya
            Google hesabınızla giriş yaparak bu koşulları kabul etmiş
            sayılırsınız.
        </p>

        <h2>1. Hizmetin tanımı</h2>
        <p>Panel şu kontrolleri sunar:</p>
        <ul>
            <li>Google arama sonuçlarında (SERP) sıralama takibi.</li>
            <li>Google AI Overview kutusunda görünürlük tespiti.</li>
            <li>Temel on-page SEO analizi (title, meta, başlıklar, linkler, vb.).</li>
            <li>Google PageSpeed Insights (Lighthouse) performans/SEO/erişilebilirlik skorları.</li>
        </ul>

        <h2>2. Erişim</h2>
        <p>
            Herhangi bir Google hesabı giriş yapıp bir hesap oluşturabilir;
            ancak yeni hesaplar bir admin onaylayana kadar panelin
            işlevlerine erişemez. Bir admin, herhangi bir zamanda ve
            gerekçe belirtmeksizin bir hesabın onayını kaldırabilir veya
            hesabı silebilir; bu durumda ilgili hesabın erişimi anında
            sonlandırılır.
        </p>

        <h2>3. Kabul edilebilir kullanım</h2>
        <p>Paneli kullanırken şunları kabul edersiniz:</p>
        <ul>
            <li>
                Yalnızca kendi sahip olduğunuz veya kontrol etme izniniz
                bulunan domain/sayfaları taratacaksınız.
            </li>
            <li>
                Google'ın hizmet şartlarını, robots.txt kurallarını ve
                geçerli mevzuatı ihlal edecek şekilde yoğun/toplu (mass)
                sorgulama yapmayacaksınız.
            </li>
            <li>
                Hizmeti başka sistemlere zarar verecek, kötüye kullanacak
                veya güvenliğini aşmaya çalışacak şekilde kullanmayacaksınız.
            </li>
        </ul>

        <h2>4. Sonuçların niteliği ve sorumluluk reddi</h2>
        <p>
            Panel, Google'ın arama sonucu sayfasını (SERP) doğrudan HTTP ile
            çeker (resmi bir SERP API'si değil); bu nedenle:
        </p>
        <ul>
            <li>
                Google, otomatik istekleri zaman zaman engelleyebilir
                (CAPTCHA/"unusual traffic"); bu durumda sonuç alınamayabilir.
            </li>
            <li>
                AI Overview kutusu genellikle istemci tarafında (JavaScript
                ile) render edildiğinden ve hesap/konum/cihaza göre
                değiştiğinden, tespiti <strong>en iyi çaba
                (best-effort)</strong> niteliğindedir; gerçekte görünen bir AI
                Overview panelde yakalanamayabilir veya panelde görünen bir
                sonuç gerçek zamanlı aramada farklılık gösterebilir.
            </li>
            <li>
                Google'ın sayfa yapısı sık değiştiği için ayrıştırma mantığı
                zamanla güncelliğini yitirebilir.
            </li>
        </ul>
        <p>
            Hizmet "olduğu gibi" (as-is) sunulur; herhangi bir doğruluk,
            kesintisizlik veya belirli bir amaca uygunluk garantisi
            verilmez. Panel sonuçlarına dayanarak alınan iş/pazarlama
            kararlarından doğacak sonuçlardan site sahibi sorumlu tutulamaz.
        </p>

        <h2>5. Üçüncü taraf hizmetlere bağımlılık</h2>
        <p>
            Hizmet, Google OAuth, Google Arama ve Google PageSpeed Insights
            API'sine bağımlıdır. Bu hizmetlerdeki kesinti, değişiklik veya
            kota kısıtlamaları (ör. API anahtarsız kullanımda düşük PSI
            kotası) panelin işlevselliğini etkileyebilir.
        </p>

        <h2>6. Hizmetin değiştirilmesi veya sonlandırılması</h2>
        <p>
            Site sahibi, paneli önceden bildirimde bulunmaksızın
            değiştirebilir, geçici olarak durdurabilir veya kalıcı olarak
            sonlandırabilir.
        </p>

        <h2>7. Değişiklikler</h2>
        <p>
            Bu koşullar zaman zaman güncellenebilir; güncel sürüm her zaman
            bu sayfada yayınlanır.
        </p>

        <h2>8. İletişim</h2>
        <p>
            Bu koşullarla ilgili sorularınız için
            @if (config('mail.from.address') && config('mail.from.address') !== 'hello@example.com')
                <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
            @else
                panel yöneticisiyle
            @endif
            iletişime geçebilirsiniz.
        </p>
    </div>
    </div>
@endsection
