🇹🇷 Türkçe | [🇬🇧 English](docker.md)

[← README'ye dön](../README.tr.md)

# Docker ile çalıştırma

Adım adım kurulum için bkz. [Docker kurulum adımları](docker-install.tr.md).
Bu belge dev ve production ortamlarının ayrıntılarını anlatır.

## Dev (önerilen)

Gereksinim: Docker + Docker Compose.

`webserver` servisi `docker-compose.yml`'de tanımlı, `proxy` adlı harici
bir Docker ağına da bağlanır (bkz. aşağıda "nginx-proxy-manager arkasında
çalıştırma"). Kullanmasanız bile bu ağı **bir kez** oluşturmanız gerekir,
yoksa `docker compose up` "network not found" hatasıyla durur:

```bash
docker network create npm_proxy   # veya .env'deki PROXY_NETWORK değeri
```

```bash
cp .env.docker.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan migrate
```

`APP_KEY` için elle bir şey yapmanıza gerek yok. `docker-compose.yml`'de
**tanımlı değildir**; `docker/php/entrypoint.sh` container ilk açıldığında
(`www/.env`'de henüz yoksa) otomatik bir anahtar üretir ve oraya yazar.
Sonraki her yeniden başlatma/oluşturmada aynı anahtar kullanılır (yoksa
her yeniden başlatmada oturumlar geçersiz olurdu). Belirli bir anahtar
kullanmak isterseniz (örn. eski bir kurulumdan taşıma) `www/.env`'deki
`APP_KEY=` satırını elle düzenleyip container'ı yeniden başlatın.

**Yazma izinleri:** `storage/`, `bootstrap/cache/` ve `vendor/` ayrı Docker
volume'leridir (aşağıdaki `volumes:`), bu yüzden host'taki dosya
sahipliğinden etkilenmezler; imaj derlenirken `www-data` kullanıcısına
verilen sahiplikte kalırlar. `database.sqlite` migration'ların yanında,
`www/database/` içinde durması gerektiği için volume olamaz; bunun yerine
`docker/php/entrypoint.sh` container her açıldığında dosyayı oluşturur ve
`www-data` için yazılabilir yapar; elle `touch`/`chmod` gerekmez. (Bu
otomasyon olmasaydı, `www/` bind-mount edildiği için host kullanıcısına ait
dosyalara gerçek Linux sunucularda `www-data` erişemez, uygulama hatayı
loglayamadan çıplak bir 500 verirdi. macOS/Windows'taki Docker Desktop
dosya paylaşımı bu sorunu gizlediği için yerelde fark edilmeyebilir.)

Panel varsayılan olarak `http://localhost:8080` adresinde çalışır
(`.env`'deki `WEB_PORT` ile değiştirilir).

Servisler:

- `app` — PHP 8.5-FPM (Composer bağımlılıkları imaj derlenirken kurulur;
  uygulama PHP 8.4+ ister).
- `worker` — kuyruk işçisi (`queue:work`); Lighthouse toplu tarama, WHOIS,
  backlink gibi arka plan işlerini çalıştırır.
- `scheduler` — zamanlanmış görevleri çalıştırır (`schedule:work`), bkz.
  [Zamanlanmış görevler](scheduled-tasks.tr.md).
- `webserver` — Nginx; `docker/nginx/default.conf` ile `www/public`'i
  document root olarak sunar, PHP isteklerini fastcgi üzerinden `app`
  servisine iletir.

`app`, `worker` ve `scheduler` aynı yerel imajı (`seo-ai-checker:local`)
kullanır; yalnızca `app` derler.

`www/` klasörü kod değişiklikleri için container'a bind-mount edilir; PHP
dosyası değişiklikleri yeniden derleme gerektirmez. `composer.json` veya
`composer.lock` değişince imajı yeniden derleyin
(`docker compose build app`). `.env`'de bir değeri değiştirdiğinizde
`docker compose up -d` yeterlidir (Compose, değişen `environment:`
değerleri için container'ı otomatik yeniden oluşturur).

## Production

Aynı `docker-compose.yml` farklı bir `--env-file` ile çalıştırılır.
`.env.prod`'da ayarlanan üç değişken davranışı değiştirir:

| Değişken | Dev (`.env`) | Prod (`.env.prod`) |
|---|---|---|
| `COMPOSER_INSTALL_FLAGS` | boş (dev bağımlılıkları da kurulur) | `--no-dev` |
| `RESTART_POLICY` | `no` | `unless-stopped` |
| `WEB_PORT` | `8080` | `80` (veya reverse proxy'nizin yönlendirdiği port) |

```bash
cp .env.docker.example .env.prod
# .env.prod'u production değerleriyle doldurun (yukarıdaki 3 değişken,
# ayrıca APP_ENV=production, APP_DEBUG=false, gerçek APP_URL,
# PSI_API_KEY, ADMIN_EMAILS vb.)

docker compose --env-file .env.prod build
docker compose --env-file .env.prod up -d

docker compose --env-file .env.prod exec app php artisan migrate --force
```

`APP_KEY` burada da elle üretilmez; bkz. yukarıdaki dev bölümündeki not
(otomatik üretilir ve `www/.env`'de saklanır).

Sunucuda tipik bir **git tabanlı deploy** akışı:

```bash
git pull
docker compose --env-file .env.prod up -d --build
docker compose --env-file .env.prod exec app php artisan migrate --force
```

`www/` bind-mount edildiği için `--build` ancak `composer.json`/
`composer.lock` veya `docker/` değiştiğinde gerçekten yeniden derleme
tetikler; yalnızca kod değişikliklerinde de `up -d --build` çalıştırmak
güvenlidir (gereksiz bir derleme Docker'ın katman önbelleği sayesinde
hızlı biter).

`--env-file`'ı (dev'de bile) **her zaman** açıkça verin. Bayrak
verilmezse `docker compose` otomatik olarak `.env` adlı dosyayı arar; bu
dev için zaten doğru davranıştır. Ama aynı dizinde hem `.env` hem
`.env.prod` varsa, production komutlarında `--env-file .env.prod`'u
atlamayın; yoksa sessizce dev ayarlarıyla çalışır.

## Hazır imajlar (derleme yok)

Her sürüm tag'i (`v1.0.0`, `v1.1.0`, ...) GitHub Actions
(`.github/workflows/docker.yml`) tarafından **linux/amd64 ve linux/arm64**
için derlenir ve tek bir imaj olarak iki kayda yayınlanır:

- GitHub Container Registry: `ghcr.io/bmericc/seo-ai-checker`
- Docker Hub: `bmericc/seo-ai-checker`

İmaj nginx ve php-fpm'i birlikte çalıştırır (port 80); kuyruk işçisi ve
zamanlayıcı aynı imajı farklı bir komutla kullanır.

Etiketler: `1.0.0`, `1.0`, `1` ve `latest`.

```bash
curl -O https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/docker-compose.release.yml
curl -o .env https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/.env.docker.example
# .env'yi düzenleyin: APP_KEY (zorunlu, aşağıya bakın), APP_URL,
# GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, ADMIN_EMAILS ve isteğe bağlı
# API anahtarları.
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"   # veya: openssl rand -base64 32

docker compose -f docker-compose.release.yml up -d
```

- **`APP_KEY` zorunludur.** Kod imajın içinde olduğu için açılışta üretilen
  bir anahtar her container yeniden oluşturmada değişir; oturumlar ve
  saklanan OAuth token'ları okunamaz olur. Container anahtar olmadan
  başlamaz.
- SQLite veritabanı ve loglar `storage` volume'ündedir
  (`/var/www/html/storage/database/database.sqlite`); bu volume'ü yedekleyin.
- `app` container'ı her açılışta bekleyen migration'ları çalıştırır, bu
  yüzden yükseltmeler ek adım gerektirmez. Kapatmak için
  `RUN_MIGRATIONS=false`.
- Sürüm sabitlemek için `.env`'de `SEO_AI_CHECKER_VERSION=1.0.0`
  (varsayılan `latest`). GHCR yerine Docker Hub kullanmak için
  `SEO_AI_CHECKER_IMAGE=bmericc/seo-ai-checker`.
- Yükseltme: `docker compose -f docker-compose.release.yml pull && docker compose -f docker-compose.release.yml up -d`.

Yeni bir sürüm yayınlamak için tag gönderin: `git tag v1.1.0 && git push origin v1.1.0`.
Workflow, `DOCKERHUB_USERNAME` ve `DOCKERHUB_TOKEN` depo secret'larını
ister (Read & Write yetkili Docker Hub kişisel erişim token'ı).

## nginx-proxy-manager (veya başka bir reverse proxy) arkasında çalıştırma

Host portuna ek olarak `webserver` servisi, `PROXY_NETWORK`/`APP_HOSTNAME`
(`.env`/`.env.prod`) ile ayarlanan harici bir Docker ağına da bağlanır;
böylece NPM, host portunu hiç kullanmadan container'a doğrudan Docker ağı
üzerinden ulaşır.

1. NPM'i çalıştıran docker-compose kurulumunun kullandığı harici ağın adını
   bulun (veya bir tane oluşturup NPM'in kendi compose dosyasında aynı adı
   `external: true` ile kullanın):
   ```bash
   docker network create npm_proxy
   ```
2. `.env.prod` içinde:
   ```
   PROXY_NETWORK=npm_proxy      # NPM ile paylaşılan ağ adı
   APP_HOSTNAME=seo-ai-checker  # NPM'in "Forward Hostname/IP" alanına yazacağınız ad
   ```
3. `docker compose --env-file .env.prod up -d` (yeniden) çalıştırıldığında
   `webserver` otomatik olarak bu ağa katılır ve `APP_HOSTNAME` ile
   erişilebilir olur (ağdaki başka bir container'dan
   `getent hosts seo-ai-checker` ile doğrulayın).
4. NPM arayüzünde yeni bir **Proxy Host** ekleyin:
   - **Domain Names**: gerçek alan adınız (örn. `seo.example.com`)
   - **Forward Hostname / IP**: `APP_HOSTNAME` değeriniz (örn. `seo-ai-checker`)
   - **Forward Port**: `80` (nginx container'ının iç portu; `.env.prod`'daki
     `WEB_PORT` değil, o yalnızca portu doğrudan host'a açmak
     istediğinizde kullanılır)
   - **SSL**: Let's Encrypt sertifikası isteyin, "Force SSL"i açın.
   - Bir "Kontrol" (SERP+sayfa içi+Lighthouse) 60 saniyeyi bulabileceği
     için (NPM'in varsayılan proxy zaman aşımı bunu kesebilir)
     **Advanced** sekmesine şu satırı ekleyin:
     ```
     proxy_read_timeout 180s;
     ```

## Kurulum (elle, Docker olmadan)

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # varsayılan SQLite kurulumu için
php artisan migrate
php artisan serve
```
