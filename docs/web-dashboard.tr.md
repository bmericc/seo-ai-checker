🇹🇷 Türkçe | [🇬🇧 English](web-dashboard.md)

[← README'ye dön](../README.tr.md)

# Web paneli

Web paneli domain/anahtar kelime kayıtlarını ve her "Kontrol Et"
çalıştırmasının sonucunu (SERP + AI Overview + sayfa içi + Lighthouse)
veritabanında saklar; böylece zaman içindeki değişimi geçmiş olarak
görebilirsiniz. Ekran ekran anlatım için bkz. [Özellik rehberi](features.tr.md).

## 1. Giriş yöntemleri

Kullanıcılar **e-posta + şifre** ile ya da **Google** ile kayıt olabilir.
Yeni hesaplar bir **yönetici onaylayana** kadar panele erişemez (bkz.
[Kullanıcı yönetimi ve yönetim paneli](admin.tr.md)). E-posta + şifreyle
kayıt olanlar önce e-postalarını doğrulamalıdır; bu yüzden e-posta
gönderimini ayarlayın (`MAIL_MAILER=smtp` ve `MAIL_*` ayarları).
`MAIL_MAILER=log` ile doğrulama ve şifre sıfırlama bağlantıları gönderilmez,
`storage/logs/laravel.log` dosyasına yazılır.

Google ile giriş isteğe bağlıdır: "Google ile giriş yap" butonunu gizlemek
için `GOOGLE_LOGIN_ENABLED=false` yapın. Giriş yapmış kullanıcılar Search
Console ve GA4 verisi için Google hesaplarını yine bağlayabilir; bu
raporları istiyorsanız aşağıdaki OAuth istemcisini koruyun. Google ile
kayıt olmuş kullanıcılar "Şifremi unuttum" ile şifre belirleyebilir.

### Google OAuth uygulaması

1. [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials)
   sayfasında yeni bir **OAuth client ID** oluşturun, tür: **Web
   application**.
2. Panelin yayınlandığı her adres için bir **Authorized redirect URI**
   ekleyin, örn. `https://sunucunuz.com/auth/google/callback`. Giriş
   başladığı adrese döner; bu yüzden ikinci bir domain (örn.
   `https://sunucunuz.com.tr`) için ayrıca
   `https://sunucunuz.com.tr/auth/google/callback` girilmelidir.
3. Çıkan **Client ID** ve **Client Secret** değerlerini ilgili `.env`
   dosyasına yazın (Docker: depo kökündeki `.env`/`.env.prod`; elle kurulum:
   `www/.env`): `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`.
4. Kendi e-postanızı `ADMIN_EMAILS` içine yazın. Bu e-posta(lar) ilk Google
   girişinde, e-posta + şifreli hesapta ise e-posta doğrulanınca
   **otomatik olarak yönetici ve onaylı** sayılır; ilk kurulumda
   kendinize erişim açmış olursunuz. Sonraki tüm kullanıcı onayları ve
   yönetici atamaları `/admin/users` panelinden yapılır, `.env`'yi tekrar
   düzenlemek gerekmez.

## 2. `.env` ayarları (web'e özel)

| Değişken | Açıklama |
|---|---|
| `APP_URL` | Panelin herkese açık adresi |
| `DB_CONNECTION`, `DB_DATABASE`/`DB_HOST`/... | SQLite (varsayılan) veya MySQL/PostgreSQL |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google OAuth uygulama bilgileri (callback adresi isteğin host'undan üretilir) |
| `GOOGLE_LOGIN_ENABLED` | `true` (varsayılan) "Google ile giriş yap" butonunu gösterir; `false` ile yalnızca e-posta + şifreli giriş kalır |
| `ADMIN_EMAILS` | Google girişinden ya da e-posta doğrulamasından sonra otomatik yönetici ve onaylı sayılacak e-postalar (virgülle ayrılmış) |
| `MAIL_MAILER` ve `MAIL_*` | E-posta doğrulama ve şifre sıfırlama için e-posta gönderimi (`log` bunları log dosyasına yazar) |
| `PSI_API_KEY` | (isteğe bağlı ama önerilir) PageSpeed Insights API anahtarı |
| `PSI_STRATEGY` | `mobile` veya `desktop` |
| `DATAFORSEO_LOGIN` / `DATAFORSEO_PASSWORD` | (isteğe bağlı) SERP, kelime metrikleri ve AI mention verisi için DataForSEO API bilgileri |
| `SERP_PROVIDER` | `auto` (varsayılan: bilgiler tanımlıysa DataForSEO, değilse doğrudan kazıma), `google`, `dataforseo` veya `fallback` (önce kazıma, yalnızca Google engellerse DataForSEO; mobil SERP yok, haftalık kontroller canlı çalışır). Docker'da `docker-compose.yml`'in `environment:` bloğunda yoksa `www/.env`'ye ekleyin |
| `CRAWLGRAPH_API_KEY` | (isteğe bağlı) Common Crawl backlink'leri için ücretsiz CrawlGraph anahtarı: `POST https://crawlgraph.com/api/v1/free-key`, gövde `{"email": "..."}`. Ayda 15 sorgu. Docker'da `docker-compose.yml`'de zaten tanımlı |
| `SERP_FALLBACK_COOLDOWN_MINUTES` | `fallback` modunda Google engelleyince kaç dakika atlanacağı (varsayılan `30`, `0` = kapalı) |

Tüm API anahtarlarının listesi:
[Docker kurulum adımları → Kullanılabilecek API'ler](docker-install.tr.md#kullanılabilecek-apiler).

## 3. Sunucuya kurulum (Docker olmadan)

Document root'u `www/public/` klasörüne yönlendirin. Örnek Nginx:

```nginx
server {
    listen 443 ssl;
    server_name sunucunuz.com;
    root /path/to/seo-ai-checker/www/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        # Bir "Kontrol" (SERP+sayfa içi+Lighthouse) 60 saniyeyi bulabilir;
        # varsayılan zaman aşımını artırın:
        fastcgi_read_timeout 180;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Notlar:

- `www/public/` **dışındaki** hiçbir klasör (`www/app/`, `www/config/`,
  `www/.env`, veritabanı dosyası) web sunucusundan doğrudan erişilebilir
  olmamalı; document root yalnızca `www/public/` olmalı.
- Google OAuth production'da **HTTPS** ister (redirect URI `https://` ile
  başlamalı); `localhost` istisnası yalnızca yerel geliştirme içindir.
- PHP'nin `max_execution_time` değerini (php.ini veya php-fpm havuz
  ayarında) en az 120 saniyeye çıkarın; "Kontrol Et" SERP + sayfa içi +
  Lighthouse'u tek istekte sırayla çalıştırır ve 20-60 saniye sürebilir.
- Standart Laravel production adımları:
  `composer install --no-dev --optimize-autoloader`,
  `php artisan migrate --force`, `php artisan config:cache`,
  `php artisan route:cache`; `storage/` ve `bootstrap/cache/` web
  sunucusu tarafından yazılabilir olmalı.

## 4. Kullanım

1. `https://sunucunuz.com/` adresine gidip e-postanızla kayıt olun ya da
   "Google ile giriş yap" ile girin. `ADMIN_EMAILS` içindeki hesaplardan
   biriyseniz (e-posta + şifreli hesapta e-postanızı doğruladıktan sonra)
   panele düşersiniz; değilse hesabınız oluşturulur ama bir yönetici onaylayana
   kadar "onay bekleniyor" sayfasını görürsünüz.
2. Bir domain ekleyin, sonra o domain için anahtar kelime(ler) ekleyin (her
   kelime için isteğe bağlı ayrı bir hedef sayfa URL'si girilebilir).
3. "Kontrol Et"e tıklayın; SERP sırası, AI Overview durumu, sayfa içi SEO ve
   Lighthouse skorları çalıştırılıp veritabanına kaydedilir.
4. Bir kelimenin ayrıntı sayfasında geçmiş kontrolleri (her çalıştırmanın
   tam sonucu) tarih sırasıyla görebilirsiniz.
5. Domain sayfasının **Backlink** sekmesi birleştirilmiş backlink profilini
   gösterir. Zamanlayıcı `backlinks:run-monthly-fetch` (DataForSEO, her
   ayın 1'i 04:00) ve `backlinks:sync-crawlgraph` (her gün 05:00; yalnızca
   en yeni Common Crawl arşivi eksik olan domainleri sorgular, ücretsiz
   kota bitince durur) görevlerini çalıştırır. Bing satırları, Bing
   Webmaster Tools'u bağlamış kullanıcılar için her site kontrolüyle gelir.
   Tüm zamanlanmış görevler: [Zamanlanmış görevler](scheduled-tasks.tr.md).
