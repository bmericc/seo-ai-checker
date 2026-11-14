[← README'ye dön](../README.tr.md)

# Docker ile kurulum adımları

Bu rehber SEO / AI Overview Checker'ı Docker ile sıfırdan kurmayı adım adım
anlatır. İki yol var:

- **A. Hazır imajla kurulum (önerilen).** Kaynak kod gerekmez. GHCR veya
  Docker Hub'daki `seo-ai-checker` imajı çekilir.
- **B. Kaynak koddan kurulum.** Geliştirme yapacaksanız ya da imajı kendiniz
  derlemek istiyorsanız.

İngilizce ayrıntılı belge: [docker.md](docker.md).

## Gereksinimler

- Docker Engine 24+ ve Docker Compose v2 (`docker compose version` ile
  kontrol edin).
- linux/amd64 veya linux/arm64 bir makine (Intel/AMD sunucu, Apple Silicon,
  Raspberry Pi 4/5 vb.).
- Uygulamaya dışarıdan erişilecekse bir alan adı ve önünde bir reverse proxy
  (nginx-proxy-manager, Traefik, Caddy...). Google ile giriş HTTPS ister.

---

## A. Hazır imajla kurulum

Tek bir imaj yayınlanır: `seo-ai-checker`. İçinde nginx + php-fpm birlikte
çalışır (port 80). Kuyruk işçisi (`worker`) ve zamanlayıcı (`scheduler`)
aynı imajı farklı komutla kullanır.

| Kayıt | İmaj |
|---|---|
| GitHub Container Registry | `ghcr.io/bmericc/seo-ai-checker` |
| Docker Hub | `bmericc/seo-ai-checker` |

Etiketler: `1.0.0`, `1.0`, `1`, `latest`.

### 1. Kurulum klasörünü hazırlayın

```bash
mkdir -p ~/seo-ai-checker
```

```bash
cd ~/seo-ai-checker
```

### 2. Compose ve örnek ayar dosyasını indirin

```bash
curl -O https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/docker-compose.release.yml
```

```bash
curl -o .env https://raw.githubusercontent.com/bmericc/seo-ai-checker/main/.env.docker.example
```

### 3. `APP_KEY` üretin

Anahtar **zorunludur**. Kod imajın içinde olduğu için her yeniden
oluşturmada otomatik anahtar değişir; oturumlar ve şifreli OAuth token'ları
okunamaz hale gelir. Anahtar yoksa container başlamaz.

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
```

Bu anahtarı güvenli bir yere yedekleyin. Kaybederseniz kayıtlı OAuth
bağlantıları yeniden kurulmalıdır.

### 4. `.env` dosyasını düzenleyin

En azından şu değerleri doldurun:

| Değişken | Açıklama |
|---|---|
| `APP_URL` | Dışarıdan erişilen adres, örn. `https://seo.example.com` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `WEB_PORT` | Host'ta açılacak port (varsayılan `8080`) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google ile giriş ve Search Console için OAuth istemcisi |
| `ADMIN_EMAILS` | Yönetici olacak Google hesapları, virgülle ayrılmış |
| `APP_LOCALE` | `tr` veya `en` |

İsteğe bağlı olanlar:

| Değişken | Ne için |
|---|---|
| `PSI_API_KEY` | PageSpeed Insights / Lighthouse kotası |
| `BING_CLIENT_ID`, `BING_CLIENT_SECRET` | Bing Webmaster Tools bağlantısı |
| `HTTP_PROXY` | SERP isteklerini proxy üzerinden göndermek |
| `SEO_AI_CHECKER_VERSION` | Sürüm sabitlemek, örn. `1.0.0` (varsayılan `latest`) |
| `SEO_AI_CHECKER_IMAGE` | Docker Hub kullanmak için `bmericc/seo-ai-checker` |
| `RUN_MIGRATIONS` | `false` yapılırsa açılışta migration çalışmaz |

### 5. OAuth yönlendirme adreslerini kaydedin

Google Cloud Console'da OAuth istemcisine şu **Authorized redirect URI**'yi
ekleyin (`APP_URL` ile aynı host olmalı):

```
https://seo.example.com/auth/google/callback
```

Bing kullanacaksanız Azure uygulamasına:

```
https://seo.example.com/auth/bing/callback
```

Birden fazla alan adıyla yayın yapıyorsanız her host için ayrı callback
ekleyin.

### 6. Başlatın

```bash
docker compose -f docker-compose.release.yml up -d
```

İlk açılışta `app` container'ı veritabanını oluşturur ve migration'ları
kendisi uygular. `worker` ve `scheduler`, `app` başladıktan sonra açılır.

### 7. Kontrol edin

```bash
docker compose -f docker-compose.release.yml ps
```

```bash
curl -I http://localhost:8080/
```

`200` dönmeli. Sorun varsa logları inceleyin:

```bash
docker compose -f docker-compose.release.yml logs -f app
```

Tarayıcıdan `APP_URL` adresini açıp `ADMIN_EMAILS` içindeki hesapla Google
ile giriş yapın.

---

## B. Kaynak koddan kurulum

### 1. Depoyu klonlayın

```bash
git clone https://github.com/bmericc/seo-ai-checker.git
```

```bash
cd seo-ai-checker
```

### 2. Proxy ağını bir kez oluşturun

`webserver` servisi harici bir Docker ağına bağlanır. Kullanmasanız da ağ
var olmalı, yoksa `network not found` hatası alırsınız:

```bash
docker network create npm_proxy
```

Ağ adını `.env` içindeki `PROXY_NETWORK` ile değiştirebilirsiniz.

### 3. Ayar dosyasını kopyalayın

Geliştirme için:

```bash
cp .env.docker.example .env
```

Production için `.env.prod` kullanın ve şunları ayarlayın:
`COMPOSER_INSTALL_FLAGS=--no-dev`, `RESTART_POLICY=unless-stopped`,
`APP_ENV=production`, `APP_DEBUG=false`, gerçek `APP_URL`.

```bash
cp .env.docker.example .env.prod
```

`APP_KEY` burada elle üretilmez; ilk açılışta `www/.env` içine otomatik
yazılır ve kalıcı kalır.

### 4. Derleyin ve başlatın

Geliştirme:

```bash
docker compose build
```

```bash
docker compose up -d
```

Production:

```bash
docker compose --env-file .env.prod up -d --build
```

`app`, `worker` ve `scheduler` tek bir yerel imajı (`seo-ai-checker:local`)
paylaşır; yalnızca `app` derlenir.

### 5. Migration çalıştırın

```bash
docker compose exec app php artisan migrate
```

Production'da:

```bash
docker compose --env-file .env.prod exec app php artisan migrate --force
```

```bash
docker compose --env-file .env.prod exec app php artisan config:cache
```

Uygulama varsayılan olarak `http://localhost:8080` adresinde açılır.

---

## Reverse proxy arkasında çalıştırma (nginx-proxy-manager)

1. `.env` / `.env.prod` içinde:
   ```
   PROXY_NETWORK=npm_proxy
   APP_HOSTNAME=seo-ai-checker
   ```
   Hazır imajla kurulumda (A yolu) `app` container'ını NPM ağına bağlayın:
   ```bash
   docker network connect npm_proxy seo-ai-checker-app-1
   ```
2. NPM'de yeni **Proxy Host** ekleyin:
   - **Domain Names**: alan adınız
   - **Forward Hostname / IP**: `APP_HOSTNAME` değeri (A yolunda container adı)
   - **Forward Port**: `80` (container içi port; `WEB_PORT` değil)
   - **SSL**: Let's Encrypt sertifikası, "Force SSL" açık
3. **Advanced** sekmesine ekleyin (bir kontrol 60 saniyeyi bulabilir):
   ```
   proxy_read_timeout 180s;
   ```

---

## Güncelleme

Hazır imaj:

```bash
docker compose -f docker-compose.release.yml pull
```

```bash
docker compose -f docker-compose.release.yml up -d
```

Migration'lar açılışta otomatik uygulanır.

Kaynak koddan:

```bash
git pull
```

```bash
docker compose --env-file .env.prod up -d --build
```

```bash
docker compose --env-file .env.prod exec app php artisan migrate --force
```

## Yedekleme

Hazır imajda SQLite veritabanı ve loglar `storage` volume'ündedir
(`/var/www/html/storage/database/database.sqlite`). Volume'ü yedekleyin:

```bash
docker run --rm -v seo-ai-checker_storage:/data -v "$PWD":/backup alpine tar czf /backup/storage-yedek.tgz -C /data .
```

Volume adı klasör adına göre değişir; `docker volume ls` ile kontrol edin.
Kaynak koddan kurulumda veritabanı `www/database/database.sqlite`
dosyasıdır. `APP_KEY` değerini de yedeğe dahil edin.

## Sık karşılaşılan sorunlar

| Belirti | Neden / çözüm |
|---|---|
| Container hemen kapanıyor, logda `APP_KEY ortam degiskeni bos` | `.env` içinde `APP_KEY` yok. 3. adımı uygulayın. |
| `network npm_proxy not found` | `docker network create npm_proxy` çalıştırın. |
| Google girişinde `redirect_uri_mismatch` | Callback adresi Google Console'da kayıtlı değil veya `APP_URL` host'u farklı. |
| Kontrol çalışırken 504 | Reverse proxy zaman aşımı; `proxy_read_timeout 180s;` ekleyin. |
| Arka plan işleri (Lighthouse toplu tarama) ilerlemiyor | `worker` container'ı çalışıyor mu: `docker compose ps`. |
| Günlük kontroller çalışmıyor | `scheduler` container'ı çalışıyor mu, loglarına bakın. |
