🇹🇷 Türkçe | [🇬🇧 English](CHANGELOG.md)

# Değişiklik günlüğü

Önemli değişiklikler burada listelenir. Sürümler
[Semantic Versioning](https://semver.org/lang/tr/) kurallarını izler. Her
sürüm tag'i için Docker imajları yayınlanır (bkz.
[Docker kurulum adımları](docs/docker-install.tr.md)).

## [Yayınlanmamış]

## [1.1.2] - 2026-11-18

### Değişenler

- Footer'da uygulama adının yanında sürüm numarası görünür ve sürüm
  notlarına bağlanır. Sürüm tek yerde (`App\Support\AppVersion`) tutulur,
  MCP sunucusu da buradan okur.
- MCP sunucusu 1.1.2 sürümünü bildirir.

## [1.1.1] - 2026-11-18

### Değişenler

- Bağımlılık güncellemeleri: `laravel/mcp` 1.2.0, `symfony/dom-crawler`
  8.1.10, `laravel/pao` 1.1.5; geliştirme araçları `phpunit/phpunit` 13.4,
  `laravel/pint` 1.32.3 ve `concurrently` 10.
- GitHub Actions güncellendi: `actions/checkout` v7,
  `docker/setup-qemu-action` v4, `docker/setup-buildx-action` v4,
  `docker/login-action` v4, `docker/metadata-action` v6 ve
  `docker/build-push-action` v7.
- MCP sunucusu 1.1.1 sürümünü bildirir.

## [1.1.0] - 2026-11-17

### Eklenenler

- **E-posta + şifre ile giriş** — Google girişinin yanında kayıt, giriş,
  e-posta doğrulama ve şifre sıfırlama. Yeni hesaplar yine yönetici onayı
  bekler. Google ile kayıt olanlar "Şifremi unuttum" ile şifre
  belirleyebilir.
- `GOOGLE_LOGIN_ENABLED` "Google ile giriş yap" seçeneğini açar veya kapatır
  (varsayılan açık). Giriş yapmış kullanıcılar Search Console ve GA4 için
  Google'ı yine bağlayabilir.
- **Mobil uygulama desteği** — "Seo Checker" Android ve iOS uygulaması
  (ayrı repo) için sunucu uç noktaları: `/mobile/meta`, `/mobile/auth/start`
  ve `/mobile/session`. Uygulama sunucu adresini sorar ve sitede sistem
  tarayıcısıyla giriş yaptırır (Google girişi de orada biter). Ardından
  paneli 2 dakika geçerli tek kullanımlık bir kodla açar. Oturum hatırlanır;
  uygulama her açılışta giriş istemez.
- **Yerel mobil ekranlar** — uygulama için salt okunur API:
  `POST /api/v1/auth/token` tek kullanımlık kodu `read` yetkili bir
  token'la değiştirir (`DELETE` iptal eder); `/api/v1/domains`,
  `/api/v1/domains/{id}/screen` ve `/api/v1/changes` domain listesini, her
  domain detay sekmesini ve son değişiklikleri döndürür. "Web'de aç"
  bağlantıları `/mobile/web` üzerinden açılır. `/mobile/meta` artık
  `mobile_api` 3 bildirir.
- **Hesap silme** — kullanıcılar hesaplarını Ayarlar'dan (ve onay/doğrulama
  bekleme sayfalarından) silebilir; uygulama `DELETE /api/v1/me` kullanır.
  Kullanıcının domainleri ve geçmişleri, bekleyen davetleri, token'ları ve
  oturumları silinir. Tek yönetici kendini silemez.
- Her sürüm tag'i için notları bu dosyadan alınan bir GitHub Release
  oluşturulur.
- Dependabot GitHub Actions, Composer, npm ve Docker taban imajını güncel
  tutar; CI'da CodeQL ve OpenSSF Scorecard çalışır.

### Değişenler

- `ADMIN_EMAILS` yükseltmesi ve bekleyen domain paylaşımları, e-posta +
  şifreli hesapta yalnızca e-posta doğrulandıktan sonra uygulanır.
- Giriş yapmışken Google bağlamak artık Google e-postası farklı olsa da
  hesabı mevcut hesaba bağlar.
- Girişsiz istekler ana sayfa yerine `/login`'e yönlenir.
- Gizlilik politikası ve kullanım koşulları e-posta + şifreyle girişi,
  paylaşımı, bağlı hesapları, yapay zekâ sağlayıcılarını ve diğer üçüncü
  taraf servisleri, mobil uygulamayı (reklam kimliği olmadan Firebase
  Crashlytics/Analytics) ve hesap silmeyi kapsar; KVKK/GDPR hakları eklendi.
- Docker imajı digest ile sabitlenmiş `php:8.5-fpm-alpine` tabanı üzerine
  kurulur; GitHub Actions commit SHA'larına sabitlendi.
- MCP sunucusu sürüm olarak 1.1.0 bildirir.

## [1.0.1] - 2026-11-14

### Güvenlik

- Dependabot'un bildirdiği bilinen açıkları gidermek için bağımlılıklar
  güncellendi: `laravel/framework` 13.42.0 (TokenGuard kimlik doğrulama
  atlatma, kritik), `league/commonmark` 2.10.7, `guzzlehttp/guzzle` 7.16.1,
  `symfony/http-foundation` 8.1.11, `phpseclib/phpseclib` 3.0.58,
  `league/flysystem` 3.36.0.

### Eklenenler

- Dokümantasyon: ekran görüntülü özellik rehberi, zamanlanmış görevler, tüm
  API anahtarlarıyla Docker kurulum adımları, tüm belgelerin Türkçesi,
  katkı rehberi ve güvenlik politikası.

## [1.0.0] - 2026-11-14

İlk herkese açık sürüm.

### Eklenenler

- **Google sıralamaları** — kelime başına ilk ~20 organik sonuç, masaüstü
  ve mobil; doğrudan kazıma veya DataForSEO API ile.
  `SERP_PROVIDER=fallback` önce kazır, yalnızca engellenen sorgular için
  DataForSEO'ya ödeme yapar.
- **Google AI Overview** — tespit, kaynak gösterilen domainler ve son 10
  kontrol üzerinden istikrar görünümü.
- **Yapay zekâ asistanı görünürlüğü** — domain başına API anahtarlarıyla
  ChatGPT, Claude, Gemini ve Perplexity kontrolleri; soru sihirbazlı Prompt
  Explorer, Share of Model matrisi, DataForSEO LLM Scraper ve LLM Mentions.
- **Site sağlığı** — robots.txt'de yapay zekâ botları, site haritası,
  llms.txt kontrolü ve üretici, HTTPS, güvenlik başlıkları, kanonik host,
  WHOIS, CrUX, kontroller arası değişim (drift), çözümüyle birlikte
  önceliklendirilmiş sorunlar.
- **Lighthouse ve sayfa içi SEO** — kelime başına ve arka planda her site
  haritası sayfası için PageSpeed Insights.
- **Kelime araçları** — kelime metrikleri (hacim, zorluk, TBM, niyet) ve
  site haritası sayfalarından site geneli kelime önerileri.
- **Rakipler** — SERP rakip keşfi, rakip kelime boşlukları ve genel rakip
  kara listesi.
- **Backlink'ler** — DataForSEO, CrawlGraph (Common Crawl) ve Bing Webmaster
  Tools verisi tek listede; trend ve yeni/kaybolan domainler.
- **Kendi verileriniz** — kullanıcı başına OAuth ile Google Search Console,
  GA4 (kanal kırılımıyla) ve Bing Webmaster Tools.
- **IndexNow** — anahtar dosyası, doğrulama ve URL/site haritası gönderimi.
- **Paylaşım ve raporlar** — salt okunur rapor linkleri ve haftalık SEO
  e-postası.
- **MCP sunucusu** — Claude, ChatGPT, Cursor ve Claude Code için OAuth
  korumalı `/mcp` uç noktası; okuma ve isteğe bağlı yazma erişimi.
- **Erişim kontrolü** — davetli Google girişi, yönetici onayı, hesaplar
  arası domain paylaşımı, host başına giriş callback'i.
- **İngilizce ve Türkçe** arayüz.
- **Docker** — nginx ve php-fpm içeren tek bir çok mimarili imaj
  (`linux/amd64`, `linux/arm64`); GHCR (`ghcr.io/bmericc/seo-ai-checker`)
  ve Docker Hub'a (`bmericc/seo-ai-checker`) yayınlanır. Kuyruk işçisi ve
  zamanlayıcı aynı imajı kullanır.

[Yayınlanmamış]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.2...HEAD
[1.1.2]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/bmericc/seo-ai-checker/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bmericc/seo-ai-checker/releases/tag/v1.0.0
