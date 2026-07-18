🇹🇷 Türkçe | [🇬🇧 English](README.md)

# SEO / AI Overview Checker

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

Web scraping ile Google arama sonuçlarında (SERP) sıralama takibi, Google
**AI Overview** kutusunda görünürlük kontrolü, temel **on-page SEO** analizi
ve **Lighthouse** (Google PageSpeed Insights) skorlarını bir araya getiren
bir **Laravel** uygulaması. Uzak bir sunucuda (veya Docker ile yerelde)
barındırılıp geçmişi veritabanında tutan, Google hesabıyla korunan bir
**web arayüzü** olarak kullanılır.

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
   veritabanında (SQLite varsayılan, MySQL de desteklenir) saklayan bir
   panel. Erişim, Google OAuth ile giriş yapıp izinli e-posta listesinde
   olma şartına bağlıdır.

## Önemli sınırlamalar ve yasal uyarı

- **Doğrudan HTTP scraping** kullanılır (SERP API'si değil). Bu yaklaşım
  bilinçli olarak tercih edilmiştir; ancak Google, otomatik istekleri sıkça
  CAPTCHA/"unusual traffic" sayfasıyla veya JavaScript doğrulaması isteyen
  bir yönlendirmeyle (`/httpservice/retry/enablejs`) engelleyebilir. Uygulama
  bu durumları tespit edip "engellendi" olarak raporlar; sonuç alamıyorsanız
  önce bunu kontrol edin.
- **AI Overview genellikle istemci tarafında (JavaScript ile) render edilir**
  ve hesap/konum/cihaza göre değişebilir. Bu araç yalnızca statik HTML
  yanıtını inceler; bu yüzden tespit **en iyi çaba (best-effort)**
  niteliğindedir ve gerçekte görünen bir AI Overview burada
  yakalanamayabilir.
- Google'ın SERP HTML yapısı sık değişir; ayrıştırma mantığı genel
  sezgisel (heuristic) kurallara dayanır ve zamanla güncellenmesi
  gerekebilir (bkz. `config/seo.php` içindeki `ai_overview_markers` ve
  `ai_overview_selectors`).
- Bu aracı yalnızca **kendi sitelerinizi/kendi izniniz olan siteleri**
  denetlemek için, makul istek sıklığıyla (`REQUEST_DELAY_MS`) kullanın.
  Google'ın hizmet şartlarını ve robots.txt kurallarını göz önünde
  bulundurun; yoğun veya toplu (mass) scraping yapılandırmayın.
- PageSpeed Insights API'sinin **API anahtarsız** kullanımında kota çok
  düşüktür ve hızla "Quota exceeded" hatasına düşebilirsiniz; gerçek
  kullanım için `PSI_API_KEY` almanız önerilir (bkz. aşağı).

## Proje yapısı

```
.
├── docker/              # Dockerfile (PHP-FPM) ve nginx konfigürasyonu
├── docker-compose.yml   # Tek dosya; dev/prod ayrimi --env-file ile yapilir
├── .env.docker.example  # docker-compose icin ortam degiskeni sablonu
└── www/                 # Laravel uygulamasinin tamami (document root: www/public)
    ├── app/
    ├── config/
    ├── database/
    ├── public/
    ├── resources/
    ├── routes/
    └── ...
```

Uygulamanın tüm kaynak kodu `www/` klasörü altındadır; `docker-compose.yml`
ve `docker/` yalnızca çalıştırma ortamına aittir.

**Önemli:** Docker altında Laravel, `www/.env` dosyasını **okumaz** —
konfigürasyon `docker-compose.yml` içindeki `environment:` bloğu üzerinden,
`--env-file` ile verilen dosyadan (dev: `.env`, prod: `.env.prod`) gerçek
ortam değişkeni olarak enjekte edilir. `www/.env.example`, yalnızca
aşağıdaki "Docker olmadan, manuel" kurulum yolu için geçerlidir.

Dev ve prod'un **aynı cihazda aynı anda çalışması beklenmediği** için
`docker-compose.yml` tek dosyadır ve volume/servis adları kasıtlı olarak
aynıdır (`vendor`, `bootstrap_cache`, vs.) — ayrım tamamen hangi
`--env-file`'ın verildiğine bağlıdır.

## Docker ile çalıştırma — dev (önerilen)

Gereksinim: Docker + Docker Compose.

`webserver` servisi, `docker-compose.yml`'de tanımlı `proxy` adlı bir
external Docker network'üne de bağlanır (bkz. aşağıdaki "nginx-proxy-manager
arkasında çalıştırma"); bu network'ü kullanmasanız bile **bir kez**
oluşturmanız gerekir, aksi halde `docker compose up` network bulunamadı
hatası verir:

```bash
docker network create npm_proxy   # veya .env'deki PROXY_NETWORK ne ise
```

```bash
cp .env.docker.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan migrate
```

`APP_KEY` için elle bir şey yapmanıza gerek yok — `docker-compose.yml`'de
tanımlı **değildir**; `docker/php/entrypoint.sh`, container ilk kez
başladığında `www/.env`'de bir anahtar yoksa otomatik üretip orada kalıcı
olarak saklar. Sonraki her yeniden başlatmada/oluşturmada aynı anahtar
kullanılmaya devam eder (aksi halde her restart'ta oturumlar geçersiz
kalırdı). Belirli bir anahtar kullanmak isterseniz (ör. eski bir kurulumdan
taşıma), `www/.env` içindeki `APP_KEY=` satırını elle düzenleyip container'ı
yeniden başlatın.

**Yazma izinleri hakkında:** `storage/`, `bootstrap/cache/` ve `vendor/`
her ikisi de ayrı Docker volume'lardır (bkz. aşağıdaki `volumes:`), bu
yüzden host'taki dosya sahipliğinden etkilenmezler — image build sırasında
`www-data` kullanıcısına chown edilmiş haliyle kalırlar. `database.sqlite`
ise `www/database/` içinde (migrations ile aynı yerde) kalması gerektiği
için volume'a alınamaz; bunun yerine `docker/php/entrypoint.sh`, container
her başladığında dosyayı oluşturup `www-data` için yazılabilir hale getirir
— elle `touch`/`chmod` gerekmez. (Bu otomasyon olmadan, `www/` bind-mount
edildiği için gerçek Linux sunucularda host kullanıcısına ait dosyalar
`www-data`'ya kapalı kalır ve uygulama loglayamadan çıplak 500 döner —
macOS/Windows'ta Docker Desktop'ın dosya paylaşımı bunu gizlediği için
yerel geliştirmede fark edilmeyebilir.)

Panel varsayılan olarak `http://localhost:8080` adresinde çalışır
(`.env` içindeki `WEB_PORT` ile değiştirilebilir).

Servisler:

- `app` — PHP 8.4-FPM (composer bağımlılıkları image build sırasında kurulur;
  `composer.lock` içindeki kilitli Symfony 8.x paketleri PHP 8.4+ gerektiriyor).
- `webserver` — Nginx, `docker/nginx/default.conf` ile `www/public`'i document
  root olarak sunar, PHP isteklerini `app` servisine fastcgi ile iletir.

Kod değişiklikleri için `www/` klasörü container'a bind-mount edilir; PHP
tarafında dosya değişikliği yeniden build gerektirmez. `composer.json` veya
`composer.lock` değiştiğinde image'ı yeniden build edin
(`docker compose build app`). `.env`'de bir değeri değiştirdiğinizde
`docker compose up -d` yeterlidir (Compose değişen `environment:` değerleri
için container'ı otomatik yeniden oluşturur).

## Docker ile çalıştırma — production

Aynı `docker-compose.yml`, farklı bir `--env-file` ile çalıştırılır.
`.env.prod`'da ayarlanan üç değişken davranışı değiştirir:

| Değişken | Dev (`.env`) | Prod (`.env.prod`) |
|---|---|---|
| `COMPOSER_INSTALL_FLAGS` | boş (dev bağımlılıkları da kurulur) | `--no-dev` |
| `RESTART_POLICY` | `no` | `unless-stopped` |
| `WEB_PORT` | `8080` | `80` (veya reverse proxy'nizin yönlendirdiği port) |

```bash
cp .env.docker.example .env.prod
# .env.prod dosyasini prod degerleriyle doldurun (yukaridaki 3 degisken +
# APP_ENV=production, APP_DEBUG=false, gercek APP_URL/GOOGLE_REDIRECT_URI,
# PSI_API_KEY, ADMIN_EMAILS, vb.)

docker compose --env-file .env.prod build
docker compose --env-file .env.prod up -d

docker compose --env-file .env.prod exec app php artisan migrate --force
```

`APP_KEY` burada da elle üretilmez — bkz. yukarıdaki dev bölümündeki not
(`www/.env`'de otomatik oluşturulup kalıcı olarak saklanır).

Sunucuda **git tabanlı deploy** için tipik akış:

```bash
git pull
docker compose --env-file .env.prod up -d --build
docker compose --env-file .env.prod exec app php artisan migrate --force
```

`www/` bind-mount edildiği için `--build` yalnızca `composer.json`/`composer.lock`
veya `docker/` değiştiğinde gerçekten yeniden build tetikler; kod-only
değişikliklerde de `up -d --build` çalıştırmak güvenlidir (gereksiz build
Docker layer cache'i sayesinde hızlı geçer).

`.env`'i **her zaman** `--env-file` ile açıkça belirtin (dev'de bile) —
`docker compose` bayrak verilmezse otomatik olarak `.env` adlı dosyayı
arar, bu da dev için zaten doğru davranıştır; ama aynı dizinde hem `.env`
hem `.env.prod` varsa prod komutlarında `--env-file .env.prod`'u atlamayın,
aksi halde sessizce dev ayarlarıyla çalışır.

## nginx-proxy-manager (veya başka bir reverse proxy) arkasında çalıştırma

`webserver` servisi, host portuna ek olarak `PROXY_NETWORK`/`APP_HOSTNAME`
(`.env`/`.env.prod`) ile ayarlanan external bir Docker network'üne de
bağlanır; bu sayede NPM, host portunu hiç kullanmadan doğrudan Docker
network'ü üzerinden container'a ulaşabilir.

1. NPM'i çalıştıran docker-compose'da (NPM'in kendi kurulumu) kullanılan
   external network'ün adını öğrenin (yoksa bir tane oluşturun ve NPM'in
   kendi compose dosyasında da aynı adı `external: true` ile kullanın):
   ```bash
   docker network create npm_proxy
   ```
2. `.env.prod` içinde:
   ```
   PROXY_NETWORK=npm_proxy      # NPM ile paylaşılan network adı
   APP_HOSTNAME=seo-ai-checker  # NPM'in "Forward Hostname/IP" alanına yazacağınız isim
   ```
3. `docker compose --env-file .env.prod up -d` (yeniden) çalıştırıldığında
   `webserver` otomatik olarak bu network'e katılır ve `APP_HOSTNAME`
   üzerinden erişilebilir hale gelir (network içindeki diğer container'lardan
   `getent hosts seo-ai-checker` ile doğrulayabilirsiniz).
4. NPM arayüzünde yeni bir **Proxy Host** ekleyin:
   - **Domain Names**: gerçek alan adınız (ör. `seo.example.com`)
   - **Forward Hostname / IP**: `APP_HOSTNAME` değeriniz (ör. `seo-ai-checker`)
   - **Forward Port**: `80` (nginx container'ının iç portu — `.env.prod`'daki
     `WEB_PORT` değil, o yalnızca host'a doğrudan port yayınlamak
     istediğinizde kullanılır)
   - **SSL**: Let's Encrypt sertifikası talep edin, "Force SSL" açın.
   - **Advanced** sekmesine, "Kontrol Et" çalıştırmasının (SERP+on-page+
     Lighthouse) 60 saniyeyi bulabilmesi nedeniyle şu satırı ekleyin
     (NPM'in kendi varsayılan proxy timeout'u bunu kesebilir):
     ```
     proxy_read_timeout 180s;
     ```

## Kurulum (Docker olmadan, manuel)

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # varsayılan SQLite için
php artisan migrate
php artisan serve
```

## Web Arayüzü

Web arayüzü, domain/anahtar kelime kayıtlarını ve her "Kontrol Et"
çalıştırmasının sonucunu (SERP + AI Overview + on-page + Lighthouse)
veritabanında saklar; böylece zaman içindeki değişimi geçmiş olarak
görebilirsiniz.

### 1. Google OAuth uygulaması oluşturun

Herhangi bir Google hesabı giriş yapıp panelde bir hesap oluşturabilir;
ancak yeni hesaplar bir **admin onaylayana kadar** panele erişemez (bkz.
"Kullanıcı yönetimi ve admin paneli" bölümü) — basit şifre yerine Google
ile kimlik doğrulama + admin onayı kullanılır.

1. [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials)
   sayfasında yeni bir **OAuth client ID** oluşturun, tür: **Web application**.
2. **Authorized redirect URI** olarak sunucunuzun adresini ekleyin, örn:
   `https://sizin-sunucunuz.com/auth/google/callback`
3. Oluşan **Client ID** ve **Client Secret** değerlerini ilgili `.env`
   dosyasına yazın (Docker: repo kökündeki `.env`/`.env.prod`; manuel kurulum:
   `www/.env`) — `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
4. `ADMIN_EMAILS` içine kendi Google e-postanızı yazın. Bu e-posta(lar) ilk
   giriş yaptığında **otomatik olarak admin + onaylı** sayılır; böylece ilk
   kurulumda kendinize erişim açmış olursunuz. Sonraki tüm kullanıcı
   onayları/admin atamaları `/admin/users` panelinden yapılır, `.env`
   düzenlemeye gerek kalmaz.

### 2. `.env` ayarları (web'e özel)

| Değişken | Açıklama |
|---|---|
| `APP_URL` | Panelin herkese açık adresi |
| `DB_CONNECTION`, `DB_DATABASE`/`DB_HOST`/... | SQLite (varsayılan) veya MySQL/PostgreSQL |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth uygulama bilgileri |
| `ADMIN_EMAILS` | İlk girişte otomatik admin+onaylı sayılacak e-postalar (virgülle ayrılmış) |
| `PSI_API_KEY` | (opsiyonel ama önerilir) PageSpeed Insights API anahtarı |
| `PSI_STRATEGY` | `mobile` veya `desktop` |

### 3. Sunucuya deploy (Docker olmadan)

Belge kökünü (document root) `www/public/` klasörüne yönlendirin. Örnek
Nginx:

```nginx
server {
    listen 443 ssl;
    server_name sizin-sunucunuz.com;
    root /path/to/seo-ai-checker/www/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        # Kontrol calistirmalari (SERP+on-page+Lighthouse) 60 saniyeyi
        # bulabilir; varsayilan zaman asimini artirin:
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
  olmamalı — yalnızca `www/public/` document root olarak ayarlanmalı.
- Google OAuth, üretimde **HTTPS** gerektirir (redirect URI `https://` ile
  başlamalı); `localhost` istisnası yalnızca yerel geliştirmede geçerlidir.
- PHP `max_execution_time` değerini (php.ini veya php-fpm pool ayarı) en az
  120 saniyeye çıkarın; "Kontrol Et" tek istekte SERP + on-page + Lighthouse
  taramasını sırayla çalıştırdığı için 20-60 saniye sürebilir.
- Standart Laravel prod adımları: `composer install --no-dev --optimize-autoloader`,
  `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`,
  `storage/` ve `bootstrap/cache/` dizinlerinin web sunucusu tarafından
  yazılabilir olması.

### 4. Kullanım

1. `https://sizin-sunucunuz.com/` adresine gidin, "Google ile giriş yap"
   ile giriş yapın. `ADMIN_EMAILS` içindeki bir hesapsanız direkt panele
   düşersiniz; değilseniz hesabınız oluşturulur ama bir admin onaylayana
   kadar "onay bekliyor" sayfasını görürsünüz.
2. Bir domain ekleyin, ardından o domain için anahtar kelime(ler) ekleyin
   (isteğe bağlı olarak her kelime için ayrı bir hedef sayfa URL'si
   belirtebilirsiniz).
3. "Kontrol Et" butonuna basın; SERP sıralaması, AI Overview durumu,
   on-page SEO ve Lighthouse skorları çalışıp veritabanına kaydedilir.
4. Anahtar kelime detay sayfasında geçmiş kontrolleri (her çalıştırmanın
   tam sonucu) kronolojik olarak görebilirsiniz.

## AI Overview tespiti nasıl çalışır?

`config/seo.php` içindeki `ai_overview_markers` metin ifadeleri (ör.
"AI overview", "Yapay zeka genel bakışı") sayfa metninde aranır. Eşleşme
bulunursa, en yakın üst kapsayıcı (ancestor) element içindeki linkler
taranarak kaynak domainler çıkarılmaya çalışılır. Google markup'ı
değiştikçe bu ifadeler ve gerekirse `ai_overview_selectors` içindeki CSS
seçiciler güncellenmelidir.

## Lighthouse entegrasyonu nasıl çalışır?

`App\Services\Lighthouse\PageSpeedInsightsClient`, Google'ın
[PageSpeed Insights v5 API](https://developers.google.com/speed/docs/insights/v5/get-started)'sine
istek atar; bu API, Lighthouse denetimini Google'ın sunucularında çalıştırıp
sonucu JSON olarak döner. Bu sayede kendi sunucunuzda Node.js/headless
Chrome kurmanıza gerek kalmaz. Denetim tipik olarak 15-40 saniye sürer ve
performans/SEO/erişilebilirlik/best-practices kategorilerinin 0-100
skorlarını verir.

## Veritabanı şeması

- `domains` — takip edilen domainler
- `keywords` — bir domaine bağlı anahtar kelimeler (opsiyonel özel URL ile)
- `checks` — her "Kontrol Et" çalıştırmasının tam sonucu (SERP, AI Overview,
  on-page, Lighthouse — JSON kolonlar Eloquent tarafından otomatik
  array'e çevrilir)

Migration'lar `www/database/migrations/` altında; kurulumda
`php artisan migrate` ile oluşturulur.

## Mimari notlar

- `App\Services\Serp\GoogleSerpScraper` — Google SERP scraping + AI Overview
  tespiti (framework'ten bağımsız, Guzzle + Symfony DomCrawler kullanır).
- `App\Services\OnPage\OnPageSeoAnalyzer` — hedef sayfa on-page analizi.
- `App\Services\Lighthouse\PageSpeedInsightsClient` — PSI API istemcisi.
- `App\Services\CheckRunner` — yukarıdaki üçünü birleştirip bir `Keyword`
  modeli için `Check` kaydı oluşturan orkestrasyon servisi (web arayüzü
  tarafından kullanılır).
- Google OAuth: `App\Http\Controllers\Auth\GoogleController` (Socialite),
  herhangi bir Google hesabı için `User` kaydı oluşturur/bulur.
- `App\Http\Middleware\EnsureUserApproved` — `approved_at` boş olan
  (onaylanmamış) kullanıcıları `/pending-approval` sayfasına yönlendirir;
  `App\Http\Middleware\EnsureUserIsAdmin` — `/admin/*` rotalarını
  `is_admin` olmayan kullanıcılara kapatır.

## Kullanıcı yönetimi ve admin paneli

- Herhangi bir Google hesabı giriş yapıp bir `User` kaydı oluşturabilir;
  varsayılan olarak `approved_at` boştur (onay bekliyor) ve panele erişemez.
- `.env`/`.env.prod` içindeki `ADMIN_EMAILS` listesindeki e-postalar ilk
  giriş yaptıklarında otomatik olarak `is_admin=true` + onaylı sayılır
  (ilk kurulumda kendinize erişim açmak için).
- Admin kullanıcılar `/admin/users` sayfasından (üst menüdeki "Kullanıcılar"
  linki) diğer kullanıcıları onaylayabilir, onayını kaldırabilir, admin
  yapabilir/admin'likten çıkarabilir veya silebilir. Bir admin kendi
  hesabı üzerinde bu işlemleri yapamaz (yanlışlıkla kendini kilitlememesi
  için); `ADMIN_EMAILS`'teki bir hesap için bu zaten sorun değildir, çünkü
  her girişte otomatik olarak admin+onaylı olarak yeniden ayarlanır.

## Geliştirme

Docker olmadan yerel geliştirme için (bkz. yukarıdaki "Kurulum" adımları):

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

php artisan serve
```

Testler:

```bash
cd www
composer test
# veya Docker uzerinden:
docker compose exec app php artisan test
```

## Lisans

[GNU General Public License v3.0 veya sonrası](LICENSE) (GPL-3.0-or-later) ile lisanslanmıştır.
