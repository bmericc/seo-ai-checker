<p class="text-secondary updated-at">Son güncelleme: 16 Kasım 2026</p>

<p>
    Bu Gizlilik Politikası, <strong>{{ config('app.name') }}</strong>
    web paneli ("panel", "hizmet") ve <strong>SEO Checker</strong> mobil
    uygulaması (App Store / Google Play'de "SEO / AI Overview Checker")
    tarafından toplanan ve işlenen kişisel verileri açıklar. Herkes
    Google hesabıyla ya da e-posta ve şifreyle kayıt olabilir; ancak
    yeni hesaplar bir admin onaylayana kadar panelin işlevlerine
    erişemez.
</p>

<h2>1. Kim veri işliyor?</h2>
<ul>
    <li>
        <strong>Panel verileri</strong> — Hesabınız, domain'leriniz ve
        kontrol sonuçlarınız, bağlandığınız panelin işleteni tarafından
        o panelin sunucusunda işlenir. Panel açık kaynaklıdır ve farklı
        kişiler tarafından kendi sunucularında çalıştırılabilir; bu
        durumda veri sorumlusu ilgili panelin işletenidir.
    </li>
    <li>
        <strong>Mobil uygulama verileri</strong> — SEO Checker mobil
        uygulaması Bahri Canlı tarafından yayınlanır. Aşağıda 7.
        bölümde açıklanan kullanım ve çökme verilerinin sorumlusu
        uygulamanın yayıncısıdır.
    </li>
</ul>
<p>
    Sorularınız ve talepleriniz için
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
        <strong>Hesap bilgileri</strong> — Ad ve e-posta adresi. Google
        ile giriş yaptığınızda bu bilgiler Google'dan alınır. E-posta
        ve şifreyle kayıt olduğunuzda şifreniz yalnızca tek yönlü
        şifrelenmiş (hash) olarak saklanır; açık hâli hiçbir zaman
        saklanmaz.
    </li>
    <li>
        <strong>Hesap durumu ve tercihler</strong> — Hesabınızın onay
        durumu, admin yetkisi, e-posta doğrulama durumu, dil ve bildirim
        tercihleri.
    </li>
    <li>
        <strong>Oturum ve erişim anahtarları</strong> — Web panelinde
        giriş durumunu korumak için oturum (session) ve CSRF çerezleri
        kullanılır; bu çerezler pazarlama veya izleme amaçlı değildir.
        Mobil uygulama ve yetkilendirdiğiniz AI asistanları (MCP) için
        erişim anahtarları (token) oluşturulur. Mobil uygulamada bu
        anahtar cihazın güvenli deposunda (iOS Keychain / Android
        Keystore) tutulur.
    </li>
    <li>
        <strong>Panelde oluşturduğunuz kayıtlar</strong> — Eklediğiniz
        domain'ler, anahtar kelimeler, rakipler, AI asistanı soruları
        ve her kontrolün sonucu (arama sıralaması, AI Overview ve AI
        asistanı görünürlüğü, on-page SEO, Lighthouse skorları, WHOIS
        ve güvenlik kontrolleri) veritabanında saklanır. Bu kayıtlar
        size aittir; yalnızca sizin paylaştığınız üyeler ve panel
        admin'leri tarafından görülebilir.
    </li>
    <li>
        <strong>Bağladığınız hesaplar (isteğe bağlı)</strong> — Google
        Search Console / Google Analytics veya Bing Webmaster Tools
        hesabınızı bağlarsanız, bu hizmetlerin erişim anahtarları
        veritabanında şifrelenmiş olarak saklanır ve yalnızca sizin
        domain'lerinizin verilerini okumak için kullanılır. Domain'e
        özel girdiğiniz AI sağlayıcı API anahtarları da şifrelenmiş
        olarak saklanır.
    </li>
    <li>
        <strong>Paylaşım bağlantıları</strong> — Bir rapor için herkese
        açık paylaşım bağlantısı oluşturursanız, bağlantıya sahip olan
        herkes o raporu giriş yapmadan görüntüleyebilir. Bağlantıyı
        istediğiniz zaman iptal edebilirsiniz.
    </li>
</ul>
<p>
    Panel, takip ettiğiniz sitelerin ziyaretçilerinden kişisel veri
    toplamaz; yalnızca sizin belirttiğiniz domain/URL'lerin herkese
    açık sayfa içeriğini ve arama sonuçlarını analiz eder.
</p>

<h2>3. Veriler hangi amaçla kullanılır?</h2>
<ul>
    <li>Kimlik doğrulama, hesap onayı ve panele erişim yetkilendirmesi.</li>
    <li>Talep ettiğiniz SEO ve AI görünürlük kontrollerinin çalıştırılması ve geçmişinin gösterilmesi.</li>
    <li>E-posta doğrulama, şifre sıfırlama ve açtığınız bildirim e-postalarının gönderilmesi.</li>
    <li>Mobil uygulamanın kararlılığının ve kullanılabilirliğinin iyileştirilmesi (7. bölüm).</li>
</ul>
<p>
    Veriler reklam veya profilleme amacıyla kullanılmaz ve üçüncü
    taraflara satılmaz.
</p>

<h2>4. Üçüncü taraf hizmetler</h2>
<p>
    Kontrolleri çalıştırmak için panel, aşağıdaki hizmetlere yalnızca
    gerekli bilgiyi (domain, URL, anahtar kelime veya soru metni)
    gönderir. Hangi hizmetlerin kullanıldığı panelin yapılandırmasına
    bağlıdır.
</p>
<ul>
    <li>
        <strong>Google OAuth</strong> — Google ile giriş ve isteğe bağlı
        Search Console / Analytics bağlantısı için. Google'ın
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">gizlilik politikası</a>
        geçerlidir.
    </li>
    <li>
        <strong>Google Arama, PageSpeed Insights ve Chrome UX Report</strong> —
        Sıralama, AI Overview tespiti ve performans ölçümleri için.
    </li>
    <li>
        <strong>DataForSEO</strong> — Yapılandırılmışsa arama sonucu ve
        anahtar kelime verileri için.
    </li>
    <li>
        <strong>AI sağlayıcıları (OpenAI, Anthropic, Google Gemini, Perplexity)</strong> —
        AI asistanı görünürlük kontrolleri için soru metinleri bu
        sağlayıcılara gönderilir. Bu metinlere kişisel veri eklememenizi
        öneririz.
    </li>
    <li>
        <strong>Microsoft Bing Webmaster Tools ve IndexNow</strong> —
        Bağlarsanız Bing verileri için; IndexNow gönderiminde
        seçtiğiniz URL'ler arama motorlarına iletilir.
    </li>
    <li>
        <strong>WHOIS ve DNS sorguları</strong> — Domain kayıt ve
        güvenlik bilgileri için herkese açık kayıtlar sorgulanır.
    </li>
    <li>
        <strong>E-posta sağlayıcısı</strong> — Doğrulama, şifre
        sıfırlama ve bildirim e-postalarının iletimi için.
    </li>
    <li>
        <strong>Google Firebase</strong> — Yalnızca mobil uygulamada;
        ayrıntılar 7. bölümde.
    </li>
</ul>

<h2>5. Veri saklama süresi</h2>
<p>
    Hesap ve kayıt verileri, siz veya bir admin silene kadar saklanır.
    Bir domain veya anahtar kelime kaydı silindiğinde ona bağlı tüm
    kontrol geçmişi de kalıcı olarak silinir. Hesabınızın onayı
    kaldırılırsa panele erişiminiz anında sonlandırılır. Hesabınızı ve
    tüm verilerinizi 8. bölümde anlatıldığı gibi istediğiniz zaman
    kendiniz silebilirsiniz.
</p>

<h2>6. Veri güvenliği</h2>
<p>
    Panele erişim HTTPS üzerinden ve onaylı hesaplarla sınırlıdır.
    Şifreler hash'lenerek, üçüncü taraf erişim anahtarları
    şifrelenerek saklanır. Paylaşılan domain'lerde okuma/yazma
    yetkisi üye bazında ayrı ayrı verilir.
</p>

<h2>7. Mobil uygulama</h2>
<p>
    SEO Checker mobil uygulaması, giriş ekranında belirttiğiniz panel
    sunucusuna bağlanır ve panel verilerinizi doğrudan o sunucudan
    okur; panel verileriniz uygulamanın yayıncısına gönderilmez.
    Uygulama, kararlılığı ve kullanılabilirliği iyileştirmek için
    Google Firebase hizmetlerini kullanır:
</p>
<ul>
    <li>
        <strong>Firebase Crashlytics</strong> — Uygulama çöktüğünde
        hata ayrıntıları (hata kaydı, uygulama sürümü, cihaz modeli,
        işletim sistemi sürümü) gönderilir.
    </li>
    <li>
        <strong>Google Analytics for Firebase</strong> — Hangi
        ekranların açıldığı, oturum süresi, uygulama sürümü, cihaz
        modeli, işletim sistemi, dil ve yaklaşık ülke gibi kullanım
        istatistikleri toplanır.
    </li>
</ul>
<p>
    Uygulama <strong>reklam kimliği (IDFA / Android Advertising ID)
    toplamaz</strong>, reklam göstermez, reklam kişiselleştirme
    sinyallerini kapalı tutar ve sizi diğer şirketlerin uygulama veya
    web sitelerinde izlemez. Firebase verilerine adınız, e-posta
    adresiniz veya panel içerikleriniz eklenmez. Bu veriler Google
    tarafından işlenir; ayrıntılar için
    <a href="https://firebase.google.com/support/privacy" target="_blank" rel="noopener">Firebase gizlilik bilgileri</a>
    sayfasına bakabilirsiniz.
</p>

<h2>8. Haklarınız</h2>
<p>
    6698 sayılı KVKK'nın 11. maddesi ve (uygulanabildiği ölçüde)
    GDPR kapsamında; verilerinize erişme, düzeltilmesini, silinmesini
    veya işlenmesinin kısıtlanmasını isteme ve itiraz etme haklarına
    sahipsiniz.
</p>
<p>
    <strong>Hesap silme:</strong> Web panelinde <em>Ayarlar → Hesabımı sil</em>
    (onay bekleyen hesaplarda bekleme sayfasındaki <em>Hesabımı sil</em>
    bağlantısı) veya mobil uygulamada <em>Ayarlar → Hesabımı sil</em> ile
    hesabınızı kalıcı olarak silebilirsiniz. Silme işlemi hesabınızı,
    sahibi olduğunuz tüm domain'leri ve kontrol geçmişini, bağlı hesap
    ve erişim anahtarlarını hemen ve geri alınamaz şekilde siler.
</p>
<p>
    Diğer talepleriniz için 1. bölümdeki iletişim adresine, hesabınızın
    e-posta adresinden yazabilirsiniz. Talepler en geç 30 gün içinde
    yanıtlanır.
</p>

<h2>9. Çocuklar</h2>
<p>
    Hizmet profesyonel kullanım içindir ve 13 yaşın altındaki
    çocuklara yönelik değildir.
</p>

<h2>10. Değişiklikler</h2>
<p>
    Bu politika zaman zaman güncellenebilir; güncel sürüm ve son
    güncelleme tarihi her zaman bu sayfada yayınlanır.
</p>
