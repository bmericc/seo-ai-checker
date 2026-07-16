@extends('layout')

@section('title', 'Gizlilik Politikası')

@section('content')
    <div class="legal">
        <h1>Gizlilik Politikası</h1>
        <p class="muted updated-at">Son güncelleme: 16 Temmuz 2026</p>

        <p>
            Bu Gizlilik Politikası, <strong>{{ config('app.name') }}</strong>
            ("panel", "hizmet") tarafından toplanan ve işlenen kişisel
            verileri açıklar. Panel, herkese açık bir ürün değildir; yalnızca
            site sahibi tarafından önceden izin verilen (allowlist'e eklenen)
            Google hesaplarının erişebildiği, sınırlı erişimli bir SEO takip
            aracıdır.
        </p>

        <h2>1. Kim veri işliyor?</h2>
        <p>
            Bu panelin işleteni, aracı kendi domain(ler)ini ve anahtar
            kelimelerini takip etmek için kullanan site sahibidir. Sorularınız
            için
            @if (config('mail.from.address') && config('mail.from.address') !== 'hello@example.com')
                <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
            @else
                panel yöneticisiyle
            @endif
            adresinden iletişime geçebilirsiniz.
        </p>

        <h2>2. Hangi veriler toplanır?</h2>
        <ul>
            <li>
                <strong>Google hesap bilgileri</strong> — Google ile giriş
                yaptığınızda Google'ın sağladığı ad ve e-posta adresi,
                hesabınızı oluşturmak ve tanımlamak için saklanır.
            </li>
            <li>
                <strong>Oturum verileri</strong> — Giriş durumunuzu korumak
                için oturum (session) ve CSRF çerezleri kullanılır. Bu
                çerezler pazarlama veya izleme amaçlı değildir.
            </li>
            <li>
                <strong>Panelde oluşturduğunuz kayıtlar</strong> — Eklediğiniz
                domain'ler, anahtar kelimeler ve her "Kontrol Et"
                çalıştırmasının sonucu (Google arama sıralaması, AI Overview
                durumu, on-page SEO analizi, Lighthouse skorları) veritabanında
                saklanır.
            </li>
        </ul>
        <p>
            Panel, ziyaretçilerden veya üçüncü taraf web sitelerinden kişisel
            veri toplamaz; yalnızca sizin belirttiğiniz domain/URL'lerin
            herkese açık arama sonuçlarını ve sayfa içeriğini analiz eder.
        </p>

        <h2>3. Veriler hangi amaçla kullanılır?</h2>
        <ul>
            <li>Kimlik doğrulama ve panele erişim yetkilendirmesi.</li>
            <li>
                Talep ettiğiniz SEO/AI Overview kontrollerinin çalıştırılması
                ve geçmişinin size gösterilmesi.
            </li>
        </ul>
        <p>Veri, reklam, profilleme veya üçüncü taraflara satış amacıyla kullanılmaz.</p>

        <h2>4. Üçüncü taraf hizmetler</h2>
        <ul>
            <li>
                <strong>Google OAuth (Google ile giriş)</strong> — kimlik
                doğrulama için kullanılır; Google'ın kendi
                <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">gizlilik politikası</a>
                geçerlidir.
            </li>
            <li>
                <strong>Google Arama</strong> — sıralama ve AI Overview
                tespiti için hedef anahtar kelimeler Google'ın herkese açık
                arama sonuç sayfasından çekilir.
            </li>
            <li>
                <strong>Google PageSpeed Insights API</strong> — Lighthouse
                skorları için, kontrol ettiğiniz sayfanın URL'si Google'ın
                PageSpeed Insights servisine gönderilir.
            </li>
        </ul>

        <h2>5. Veri saklama süresi</h2>
        <p>
            Hesap ve kayıt verileri, siz veya panel yöneticisi silene kadar
            saklanır. Bir domain veya anahtar kelime kaydını sildiğinizde,
            ona bağlı tüm kontrol geçmişi de kalıcı olarak silinir.
            Google hesabınız erişim listesinden (allowlist) çıkarılırsa
            oturumunuz anında sonlandırılır; ancak önceden oluşturulmuş
            hesap/kayıt verilerinin tamamen silinmesi için panel yöneticisiyle
            iletişime geçmeniz gerekir.
        </p>

        <h2>6. Veri güvenliği</h2>
        <p>
            Erişim, yalnızca izin verilen Google hesaplarıyla ve HTTPS
            üzerinden yapılan girişle sınırlıdır. Uygulama kodu ve
            veritabanı, herkese açık olmayan bir sunucu ortamında barındırılır.
        </p>

        <h2>7. Haklarınız</h2>
        <p>
            Hakkınızda saklanan verilere erişim, düzeltme veya silinme talebi
            için yukarıdaki iletişim kanalını kullanabilirsiniz.
        </p>

        <h2>8. Değişiklikler</h2>
        <p>
            Bu politika zaman zaman güncellenebilir; önemli değişiklikler bu
            sayfada yayınlanır.
        </p>
    </div>
@endsection
