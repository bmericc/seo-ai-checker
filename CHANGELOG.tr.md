🇹🇷 Türkçe | [🇬🇧 English](CHANGELOG.md)

# Değişiklik günlüğü

Önemli değişiklikler burada listelenir. Sürümler
[Semantic Versioning](https://semver.org/lang/tr/) kurallarını izler. Her
sürüm tag'i için Docker imajları yayınlanır (bkz.
[Docker kurulum adımları](docs/docker-install.tr.md)).

## [Yayınlanmamış]

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

[Yayınlanmamış]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/bmericc/seo-ai-checker/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/bmericc/seo-ai-checker/releases/tag/v1.0.0
