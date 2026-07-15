# SEO / AI Overview Checker

Web scraping ile Google arama sonuçlarında (SERP) sıralama takibi, Google
**AI Overview** kutusunda görünürlük kontrolü, temel **on-page SEO** analizi
ve **Lighthouse** (Google PageSpeed Insights) skorlarını bir araya getiren
bağımsız bir PHP aracı. Hem tek seferlik kontroller için bir **CLI**, hem de
uzak bir sunucuda barındırılıp geçmişi veritabanında tutan, Google hesabıyla
korunan bir **web arayüzü** olarak kullanılabilir.

## Özellikler

1. **SERP sıralaması** — Google'ın ilk ~20 organik sonucunu çeker, hedef
   domaininizin kaçıncı sırada çıktığını raporlar.
2. **AI Overview kontrolü** — Sonuç sayfasında bir AI Overview kutusu olup
   olmadığını, varsa kutu içinde hangi domainlerin kaynak olarak
   gösterildiğini ve kendi domaininizin bu kaynaklar arasında olup
   olmadığını raporlar.
3. **On-page SEO analizi** — hedef sayfayı çekip title/meta description
   uzunluğu, H1/H2 sayısı, kelime yoğunluğu, `alt` etiketi eksik görseller,
   iç/dış link sayısı ve yapısal veri (JSON-LD) varlığı gibi temel
   kontrolleri yapar.
4. **Lighthouse (Google PageSpeed Insights API)** — performans, SEO,
   erişilebilirlik ve best-practices skorlarını alır. Sunucuda Chrome/Node.js
   kurulumu **gerekmez**; denetim Google'ın kendi sunucularında çalışır.
5. **Web arayüzü** — domain/anahtar kelime ekleyip "Kontrol Et" ile tüm
   kontrolleri tek seferde çalıştırabileceğiniz, sonuçları ve geçmişi
   SQLite/MySQL'de saklayan bir panel. Erişim, Google OAuth ile giriş yapıp
   izinli e-posta listesinde olma şartına bağlıdır.

## Önemli sınırlamalar ve yasal uyarı

- **Doğrudan HTTP scraping** kullanılır (SERP API'si değil). Bu yaklaşım
  bilinçli olarak tercih edilmiştir; ancak Google, otomatik istekleri sıkça
  CAPTCHA/"unusual traffic" sayfasıyla veya JavaScript doğrulaması isteyen
  bir yönlendirmeyle (`/httpservice/retry/enablejs`) engelleyebilir. Araç bu
  durumları tespit edip "engellendi" olarak raporlar; sonuç alamıyorsanız
  önce bunu kontrol edin.
- **AI Overview genellikle istemci tarafında (JavaScript ile) render edilir**
  ve hesap/konum/cihaza göre değişebilir. Bu araç yalnızca statik HTML
  yanıtını inceler; bu yüzden tespit **en iyi çaba (best-effort)**
  niteliğindedir ve gerçekte görünen bir AI Overview burada
  yakalanamayabilir.
- Google'ın SERP HTML yapısı sık değişir; ayrıştırma mantığı genel
  sezgisel (heuristic) kurallara dayanır ve zamanla güncellenmesi
  gerekebilir (bkz. `config/ai_overview_markers.php` ve
  `config/ai_overview_selectors.php`).
- Bu aracı yalnızca **kendi sitelerinizi/kendi izniniz olan siteleri**
  denetlemek için, makul istek sıklığıyla (`--delay` / `REQUEST_DELAY_MS`)
  kullanın. Google'ın hizmet şartlarını ve robots.txt kurallarını göz önünde
  bulundurun; yoğun veya toplu (mass) scraping yapılandırmayın.
- PageSpeed Insights API'sinin **API anahtarsız** kullanımında kota çok
  düşüktür ve hızla "Quota exceeded" hatasına düşebilirsiniz; gerçek
  kullanım için `PSI_API_KEY` almanız önerilir (bkz. aşağı).

## Kurulum (ortak adımlar)

```bash
composer install
cp .env.example .env
# .env dosyasini ihtiyaciniza gore duzenleyin
```

Bu adım hem CLI hem web arayüzü için gereklidir.

## CLI Kullanımı

```bash
# Tek anahtar kelime, tek domain
php bin/seo-check --domain=example.com --keyword="anahtar kelime"

# Birden fazla anahtar kelime
php bin/seo-check --domain=example.com -k "anahtar kelime 1" -k "anahtar kelime 2"

# Dosyadan anahtar kelime listesi (bkz. keywords.example.txt)
php bin/seo-check --domain=example.com --keywords-file=keywords.example.txt

# On-page analizi atla, sadece SERP/AI Overview kontrolü yap
php bin/seo-check --domain=example.com --keywords-file=keywords.example.txt --skip-onpage

# Sonuçları JSON olarak da kaydet
php bin/seo-check --domain=example.com --keywords-file=keywords.example.txt --json=rapor.json

# Dil/bölge, gecikme ve proxy ayarları
php bin/seo-check --domain=example.com --keyword="php nedir" --hl=en --gl=us --delay=6000 --proxy=http://127.0.0.1:8080
```

### Seçenekler

| Seçenek | Açıklama | Varsayılan |
|---|---|---|
| `--domain`, `-d` | Takip edilen domain (zorunlu) | — |
| `--keyword`, `-k` | Kontrol edilecek anahtar kelime (tekrarlanabilir) | — |
| `--keywords-file`, `-f` | `keyword` veya `keyword\|url` satırları içeren dosya | — |
| `--url`, `-u` | On-page analiz için varsayılan sayfa | `https://{domain}/` |
| `--skip-onpage` | On-page analizi devre dışı bırakır | kapalı |
| `--hl` | Google arayüz dili | `tr` (`.env`'den) |
| `--gl` | Google bölge kodu | `tr` (`.env`'den) |
| `--delay` | İstekler arası bekleme (ms) | `4000` |
| `--proxy` | HTTP proxy | — |
| `--user-agent` | Özel User-Agent | Chrome masaüstü UA |
| `--json` | Sonuçları ayrıca JSON dosyasına yazar | — |

Not: CLI, Lighthouse denetimi yapmaz; bu yalnızca web arayüzünde mevcuttur.

Anahtar kelime dosyasındaki satır başına isteğe bağlı `|url` kısmı,
o anahtar kelime için on-page analizinin hangi sayfada yapılacağını
belirtmenizi sağlar (ör. o kelimeyle hedeflenen iniş sayfası).

## Web Arayüzü (uzak sunucuda barındırma)

Web arayüzü, domain/anahtar kelime kayıtlarını ve her "Kontrol Et"
çalıştırmasının sonucunu (SERP + AI Overview + on-page + Lighthouse)
veritabanında (varsayılan: SQLite, sıfır kurulum) saklar; böylece zaman
içindeki değişimi geçmiş olarak görebilirsiniz.

### 1. Google OAuth uygulaması oluşturun

Panel herkese açık bir sunucuda çalışacağı için erişim, Google ile giriş +
e-posta allowlist ile korunur (basit şifre yerine).

1. [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials)
   sayfasında yeni bir **OAuth client ID** oluşturun, tür: **Web application**.
2. **Authorized redirect URI** olarak sunucunuzun adresini ekleyin, örn:
   `https://sizin-sunucunuz.com/auth/google/callback`
3. Oluşan **Client ID** ve **Client Secret** değerlerini `.env` dosyasına
   yazın (bkz. aşağıdaki tablo).
4. `ALLOWED_GOOGLE_EMAILS` içine panele giriş yapabilecek Google
   e-postalarını virgülle ayırarak yazın. **Bu liste boşsa kimse giriş
   yapamaz** (varsayılan olarak erişim kapalıdır, güvenli taraf budur).

### 2. `.env` ayarları (web'e özel)

| Değişken | Açıklama |
|---|---|
| `APP_DEBUG` | `true` ise hata detaylarını tarayıcıda gösterir (yalnızca gelistirmede acin) |
| `DB_DRIVER`, `DB_PATH` veya `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` | SQLite (varsayılan) veya MySQL |
| `GOOGLE_OAUTH_CLIENT_ID` / `GOOGLE_OAUTH_CLIENT_SECRET` / `GOOGLE_OAUTH_REDIRECT_URI` | Google OAuth uygulama bilgileri |
| `ALLOWED_GOOGLE_EMAILS` | Giriş izni verilen e-postalar (virgülle ayrılmış) |
| `PSI_API_KEY` | (opsiyonel ama önerilir) PageSpeed Insights API anahtarı |
| `PSI_STRATEGY` | `mobile` veya `desktop` |

### 3. Sunucuya deploy

Belge kökünü (document root) `public/` klasörüne yönlendirin. Örnek Nginx:

```nginx
server {
    listen 443 ssl;
    server_name sizin-sunucunuz.com;
    root /path/to/seo-ai-checker/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # Kontrol calistirmalari (SERP+on-page+Lighthouse) 60 saniyeyi
        # bulabilir; varsayilan zaman asimini artirin:
        fastcgi_read_timeout 180;
    }
}
```

Notlar:

- `public/` **dışındaki** hiçbir klasör (`src/`, `config/`, `.env`, veritabanı
  dosyası) web sunucusundan doğrudan erişilebilir olmamalı — yalnızca
  `public/` document root olarak ayarlanmalı.
- Google OAuth, üretimde **HTTPS** gerektirir (redirect URI `https://` ile
  başlamalı); `localhost` istisnası yalnızca yerel geliştirmede geçerlidir.
- PHP `max_execution_time` değerini (php.ini veya php-fpm pool ayarı) en az
  120 saniyeye çıkarın; "Kontrol Et" tek istekte SERP + on-page + Lighthouse
  taramasını sırayla çalıştırdığı için 20-60 saniye sürebilir.
- Veritabanı dosyası (`database/*.sqlite`) ve `.env` dosyasının yazılabilir
  ve web sunucusu tarafından **servis edilemez** olduğundan emin olun.

### 4. Kullanım

1. `https://sizin-sunucunuz.com/` adresine gidin, "Google ile giriş yap"
   akışına yönlendirilirsiniz (yalnızca `ALLOWED_GOOGLE_EMAILS` içindeki
   hesaplar kabul edilir).
2. Bir domain ekleyin, ardından o domain için anahtar kelime(ler) ekleyin
   (isteğe bağlı olarak her kelime için ayrı bir hedef sayfa URL'si
   belirtebilirsiniz).
3. "Kontrol Et" butonuna basın; SERP sıralaması, AI Overview durumu,
   on-page SEO ve Lighthouse skorları çalışıp veritabanına kaydedilir.
4. Anahtar kelime detay sayfasında geçmiş kontrolleri (her çalıştırmanın
   tam sonucu) kronolojik olarak görebilirsiniz.

## AI Overview tespiti nasıl çalışır?

`config/ai_overview_markers.php` içindeki metin ifadeleri (ör. "AI overview",
"Yapay zeka genel bakışı") sayfa metninde aranır. Eşleşme bulunursa, en
yakın üst kapsayıcı (ancestor) element içindeki linkler taranarak kaynak
domainler çıkarılmaya çalışılır. Google markup'ı değiştikçe bu ifadeler ve
gerekirse `config/ai_overview_selectors.php` içindeki CSS seçiciler
güncellenmelidir.

## Lighthouse entegrasyonu nasıl çalışır?

`SeoAiChecker\Lighthouse\PageSpeedInsightsClient`, Google'ın
[PageSpeed Insights v5 API](https://developers.google.com/speed/docs/insights/v5/get-started)'sine
istek atar; bu API, Lighthouse denetimini Google'ın sunucularında çalıştırıp
sonucu JSON olarak döner. Bu sayede kendi sunucunuzda Node.js/headless
Chrome kurmanıza gerek kalmaz. Denetim tipik olarak 15-40 saniye sürer ve
performans/SEO/erişilebilirlik/best-practices kategorilerinin 0-100
skorlarını verir.

## Veritabanı şeması

Web arayüzü üç tablo kullanır: `domains`, `keywords` ve her "Kontrol Et"
çalıştırmasının tam sonucunu (SERP, AI Overview, on-page, Lighthouse) satır
olarak tutan `checks`. Şema, ilk çalıştırmada (`config/container.php` →
`buildPdo()`) otomatik oluşturulur; elle migration çalıştırmanız gerekmez.

## Geliştirme

```bash
find src config public -name '*.php' -exec php -l {} \;

# Yerel test sunucusu
php -S 127.0.0.1:8000 -t public
```
