🇹🇷 Türkçe | [🇬🇧 English](architecture.md)

[← README'ye dön](../README.tr.md)

# Mimari

## Proje yapısı

```
.
├── docker/              # Dockerfile (PHP-FPM) ve nginx yapılandırması
├── docker-compose.yml   # Tek dosya; dev/prod --env-file ile ayrılır
├── .env.docker.example  # docker-compose için ortam değişkeni şablonu
└── www/                 # Laravel uygulamasının tamamı (document root: www/public)
    ├── app/
    ├── config/
    ├── database/
    ├── public/
    ├── resources/
    ├── routes/
    └── ...
```

Uygulamanın tüm kaynak kodu `www/` altındadır; `docker-compose.yml` ve
`docker/` yalnızca çalışma ortamına aittir.

**Önemli:** Docker altında yapılandırma, `docker-compose.yml`'deki
`environment:` bloğu üzerinden gerçek ortam değişkenleri olarak verilir;
kaynağı `--env-file` ile verilen dosyadır (dev: `.env`, prod: `.env.prod`).
Bunlar her zaman `www/.env`'nin önüne geçer. Laravel, container'da
tanımlı olmayan değişkenler için `www/.env`'yi yine okur: entrypoint
`APP_KEY`'i oraya yazar, `environment:` listesinde olmayan değişkenler de
oraya eklenebilir. `www/.env.example` yalnızca "elle, Docker olmadan"
kurulum yolu için geçerlidir (bkz. [Docker ile çalıştırma](docker.tr.md)).

Dev ve prod'un **aynı makinede aynı anda çalışması beklenmediği** için
`docker-compose.yml` tek dosyadır ve volume/servis adları bilerek aynıdır
(`vendor`, `bootstrap_cache` vb.); ayrım tamamen hangi `--env-file`'ın
verildiğine bağlıdır.

## İki seviyede kontrol

Uygulama kontrolleri birbirinden bağımsız iki seviyede çalıştırır; her
birinin kendi yönetici servisi ve geçmiş tablosu vardır:

- **Anahtar kelime seviyesi** (`App\Services\CheckRunner`, tablo `checks`):
  belirli bir `Keyword`'e (domain + arama ifadesi + isteğe bağlı hedef URL)
  bağlıdır. O URL için SERP sırası, AI Overview, sayfa içi SEO ve
  Lighthouse.
- **Domain seviyesi** (`App\Services\DomainCheckRunner`, tablo
  `domain_checks`): tek bir kelimeden bağımsız, tüm `Domain`'e bağlıdır.
  robots.txt'de yapay zekâ botu erişimi, sitemap.xml, llms.txt, güvenlik
  başlıkları, kanonik host, CrUX Core Web Vitals, Search Console/Analytics/
  Bing backlink verisi ve kelime önerileri.

## AI Overview tespiti nasıl çalışır

`config/seo.php` içindeki `ai_overview_markers` ifadeleri (örn. "AI
overview") sayfa metninde aranır. Eşleşme bulunursa, en yakın üst kapsayıcı
öğedeki linkler taranarak kaynak gösterilen domainler çıkarılmaya çalışılır.
Google'ın işaretlemesi değiştikçe bu ifadelerin (ve gerekirse
`ai_overview_selectors` içindeki CSS seçicilerin) güncellenmesi gerekir.

## Lighthouse entegrasyonu nasıl çalışır

`App\Services\Lighthouse\PageSpeedInsightsClient`, Google'ın
[PageSpeed Insights v5 API](https://developers.google.com/speed/docs/insights/v5/get-started)'sine
istek gönderir. Bu API Lighthouse denetimini Google'ın kendi
sunucularında çalıştırır ve sonucu JSON olarak döndürür. Böylece kendi
sunucunuza Node.js/headless Chrome kurmanız gerekmez. Denetim genellikle
15-40 saniye sürer ve performans/SEO/erişilebilirlik/en iyi uygulamalar
kategorileri için 0-100 skor döndürür.

Domain/kelime sayfasından istenen kontrolün yanı sıra, Lighthouse (ve
sayfa içi SEO) denetimleri sitemap URL'si başına kuyruğa da alınabilir;
bkz. aşağıda [Sitemap URL kuyruk işleri](#sitemap-url-kuyruk-işleri).

## Veritabanı şeması

- `users` — giriş yapmış her Google hesabı için bir satır. `approved_at`/
  `is_admin` (panel erişimi), saklanan Google OAuth token'ları (GSC/GA4
  için), saklanan Bing OAuth token'ları (Bing backlink'leri için) ve
  `locale` (ilk girişte Google hesabından alınır,
  `App\Http\Middleware\SetLocale` ile senkron tutulur, aşağıya bakın).
- `domains` — takip edilen domainler, bir `user_id`'ye aittir. Seçilen
  `ga4_property_id`, gizlenen kelime önerileri listesi ve son WHOIS
  sorgusu (`whois_registrar`, `whois_registered_at`, `whois_expires_at`,
  ham yanıtın tamamı `whois_raw`, `whois_error`, `whois_checked_at`)
  burada tutulur.
- `keywords` — bir domaine bağlı anahtar kelimeler (isteğe bağlı özel URL
  ile).
- `checks` — kelime seviyesindeki her "Kontrol Et" çalıştırmasının tam
  sonucu (SERP, AI Overview, sayfa içi, Lighthouse).
- `domain_checks` — domain seviyesindeki her kontrolün tam sonucu (yapay
  zekâ botları, sitemap, llms.txt, güvenlik başlıkları, kanonik host, CrUX,
  GSC, GA4, Bing backlink'leri, önerilen kelimeler).
- `backlink_snapshots` — her DataForSEO backlink çekimi için bir satır
  (özet sayılar, en çok link veren domainler, anchor'lar, yeni/kaybolan
  domainler, maliyet, hata). Domain başına son 52 tanesi tutulur.
  `domains.backlinks_requested_at` kuyruktaki bir çekimi işaretler.
- `crawlgraph_snapshots` — domain ve Common Crawl sürümü başına bir satır
  (link veren domainler, otorite, sıra, önceki sürüme göre eklenen/çıkan
  domainler); `(domain_id, release_id)` üzerinde benzersiz.
- `domain_competitors` — bir kullanıcının bir domain için takip ettiği,
  yok saydığı ya da elle eklediği rakipler.
- `sitemap_urls` — bir domainin sitemap.xml'inde bulunan URL'ler
  (`SitemapUrlSync` ile senkronlanır); her birinin isteğe bağlı kuyruktaki
  Lighthouse/sayfa içi tarama sonuçları vardır.

JSON sütunları Eloquent tarafından otomatik olarak diziye çevrilir.
Migration'lar `www/database/migrations/` altındadır; kurulumda
`php artisan migrate` ile oluşturulur.

## Servisler

Tüm servisler `App\Services\` altında, her entegrasyon için kendi
namespace'inde durur ve mümkün olduğunca framework'ten bağımsızdır (düz
değerler alır, tipli sonuç nesneleri döndürür).

### Yönetim

- `App\Services\CheckRunner` — SERP/AI Overview, sayfa içi SEO ve
  Lighthouse'u bir `Keyword` için tek bir `Check` kaydında birleştirir.
- `App\Services\DomainCheckRunner` — robots.txt, sitemap.xml, llms.txt,
  güvenlik başlıkları, kanonik host, CrUX, GSC, GA4, Bing backlink'leri ve
  kelime önerilerini bir `Domain` için tek bir `DomainCheck` kaydında
  birleştirir.
- `App\Services\SharedDomainCheckLookup` — kimin sorduğuna bağlı olmayan
  domain geneli bilgiler için (yapay zekâ botları, llms.txt, güvenlik
  başlıkları, kanonik host, CrUX) aynı domaini zaten kontrol etmiş
  *herhangi bir* kullanıcının yakın tarihli sonucunu yeniden kullanır.
  GSC/GA4/Bing (kullanıcı başına OAuth) ve kelime önerileri (kullanıcı
  başına gizleme listesi) asla paylaşılmaz, her zaman yeniden çalışır.
- `App\Services\Sitemap\SharedSitemapUrlLookup` — sitemap URL'si
  karşılığı: bir URL'nin kuyruktaki Lighthouse/sayfa içi tarama sonuçlarını
  kullanıcılar arasında paylaşır.

### Analitik (mevcut veriden türetilir, yeni sorgu yok)

Bunların hepsi yukarıdaki servislerin topladığı `Check`/`DomainCheck`
satırları üzerinde saf hesaplamadır; yeni API çağrısı veya tablo yoktur.

- `App\Services\Analytics\ScoreHistoryBuilder` — bir domainin veya
  kelimenin `Check` geçmişini (ve domainin `DomainCheck` geçmişini) domain/
  kelime sayfalarındaki grafikler için günlük zaman serilerine çevirir: AI
  Overview görünürlük %'si, Lighthouse skorları, ortalama SERP sırası, CrUX
  yükleme süresi metrikleri, Search Console tıklama/gösterim/TO/sıra, GA4
  oturum/kullanıcı.
- `App\Services\Analytics\CompetitorAnalyzer` — rakip analizi kartı: takip
  edilen her kelimenin *en son* `Check.organic_results` verisine bakar ve
  takip edilen domainin yanında en sık tekrar eden domainleri ortalama/en
  iyi sıralarıyla gösterir. Sıralama ham görünme sayısına değil, paylaşılan
  farklı kelime sayısına göredir; sık yeniden kontrol edilen tek bir kelime
  sıralamayı domine edemez.
- `App\Services\Analytics\AiOverviewVolatility` — engellenmemiş son 10
  kontrolde kelime başına AI Overview istikrarı: tetiklenme oranı, kaynak
  gösterilme oranı (kaynak / gösterilen AI Overview) ve değişim oranı
  (yok/var/kaynak durumunun ardışık kontroller arasında ne sıklıkla
  değiştiği; istikrarlı/orta/oynak). MCP'de de sunulur (`get_domain`,
  `list_keywords`).
- `App\Services\Analytics\LlmShareOfModel` — "Share of Model" matrisi: son
  90 günün saklanan `PromptExploration` cevaplarından, her asistanın
  cevaplarında domainin, en önemli SERP rakiplerinin ve sık anılan diğer
  host'ların geçme oranı (`Llm\LlmMentions` ile domain adına göre
  eşleştirilir). MCP'de de sunulur (`get_competitors`).
- `App\Services\Analytics\VisibilityChanges` — her kelimenin
  engellenmemiş son kontrolünü pencere öncesindeki (varsayılan 7 gün) son
  kontrolle, asistan anılma oranlarını önceki pencereyle, site kontrolünü
  ve Search Console tıklamalarını karşılaştırır; kayıp ve kazançları
  etkisine göre sıralı döndürür. MCP `get_visibility_changes`.
- `App\Services\Llm\PromptSuggester` — Prompt Explorer'ın "soru öner"
  sihirbazı: domainin ilk uygun LLM anahtarına (Claude, ChatGPT, Gemini,
  sonra Perplexity) tek istekle, seçilen kelimelerden ve arama niyetinden
  marka adı içermeyen müşteri soruları ürettirir.

### SEO / SERP

- `App\Services\Serp\GoogleSerpScraper` — Google SERP kazıma + AI Overview
  tespiti (Guzzle + Symfony DomCrawler); CAPTCHA/"unusual traffic"
  engellerini tespit edip raporlar. Google'a istekler arasında
  `REQUEST_DELAY_MS` uygulayan `Serp\GoogleRequestThrottle` ile birlikte
  çalışır.
- `App\Services\Serp\DataForSeoSerpScraper` — aynı `SerpScraper`
  sözleşmesi, ücretli DataForSEO Google Organic API üzerinden (canlı ve
  Standard kuyruk).
- `App\Services\Serp\FallbackSerpScraper` — `SERP_PROVIDER=fallback`:
  önce kazımayı dener, sorguyu yalnızca sonuç engellendiğinde DataForSEO'ya
  gönderir, ardından `SERP_FALLBACK_COOLDOWN_MINUTES` boyunca Google'ı
  atlar. Hangi uygulamanın bağlanacağına `AppServiceProvider` karar verir.
- `App\Services\OnPage\OnPageSeoAnalyzer` — hedef sayfanın sayfa içi
  analizi: title/meta description uzunluğu, H1/H2 ve başlık hiyerarşisi,
  kelime yoğunluğu, görsel `alt` kapsamı, iç/dış linkler, yapısal veri
  (JSON-LD şema türleri, kullanımdan kalkanlar dahil), Open Graph/Twitter
  Card etiketleri, kanonik durum ve hreflang etiketleri/sorunları.
- `App\Services\CanonicalHost\CanonicalHostChecker` — bir domainin kanonik
  host'unu çözer (örn. `example.com`, `www.example.com`'a yönleniyor mu);
  diğer domain seviyesi kontroller için sistem geneli varsayılan host
  olarak kullanılır.
- `App\Services\Robots\RobotsTxtChecker` — robots.txt'de bilinen yapay
  zekâ botlarını (GPTBot, ClaudeBot, Google-Extended vb.) etkileyen
  allow/disallow kurallarını kontrol eder.
- `App\Services\Llms\LlmsTxtChecker` — `llms.txt` dosyasının varlığını
  kontrol eder ve içeriğinin önizlemesini döndürür.
- `App\Services\Llms\LlmsTxtValidator` — bulunan `llms.txt`'nin biçim
  kontrolü (`LlmsTxtChecker` çalıştırır, `llms_txt.validation` altında
  saklanır): HTML yanıtlar (SPA geri dönüşleri), boş dosyalar veya eksik
  `# Başlık` için hata; eksik `> özet`, hiç `- [Başlık](URL)` linki
  olmaması, `## Bölüm` başlıkları dışındaki linkler, bozuk liste satırları
  ve çoğunlukla dış linkler için uyarı. Hatalar "llms.txt geçersiz" sağlık
  sorunu olarak görünür.
- `App\Services\Llms\LlmsTxtGenerator` — aktif `sitemap_urls`'ten,
  ilk yol segmentine göre gruplanmış, varsa sayfa raporu başlık/meta
  açıklamalarını kullanan bir llms.txt taslağı (llmstxt.org biçimi)
  oluşturur. Hiçbir şey kaydedilmez; `/domains/{id}/llms-txt` adresinde ve
  MCP'de (`generate_llms_txt`) gösterilir.
- `App\Services\Sitemap\SitemapChecker` — sitemap.xml'i (sitemap index
  dosyaları dahil) çeker, doğrular ve URL'leri sayar;
  `Sitemap\SitemapUrlSync` bulunan URL'leri `sitemap_urls` tablosuna
  senkronlar.
- `App\Services\Security\SecurityHeadersChecker` — yaygın güvenlik yanıt
  başlıklarını ve HTTP'nin HTTPS'e yönlenip yönlenmediğini kontrol eder.
- `App\Services\Keywords\KeywordSuggester` — bir domain için ek kelimeler
  önerir (zaten takip edilen veya gizlenenler hariç).
- `App\Services\Drift\DomainCheckDrift` — ardışık `DomainCheck` kayıtlarını
  karşılaştırarak çalıştırmalar arasında neyin değiştiğini gösterir.
- `App\Services\Whois\WhoisChecker` —
  [`bahricanli/domainhunter`](https://github.com/bahricanli/laravel-domainhunter)
  paketini (WHOIS/RDAP sorgusu + domain adı ayrıştırma, `intl` PHP
  eklentisini gerektirir) sarar; bir domainin kayıt firmasını, kayıt ve
  bitiş tarihini çözer. Her zaman `App\Jobs\RunDomainWhoisLookup`
  üzerinden çalışır (bkz. [Arka plan işleri](#arka-plan-işleri)), asla
  istek içinde değil; WHOIS sorguları kayıt firması sunucularına doğrudan
  TCP/RDAP bağlantılarıdır, yavaş veya erişilemez olabilir.

### Performans ve gerçek kullanıcı metrikleri

- `App\Services\Lighthouse\PageSpeedInsightsClient` — PSI v5 API istemcisi
  (bkz. [yukarıda](#lighthouse-entegrasyonu-nasıl-çalışır)).
- `App\Services\Crux\CrUxChecker` — Chrome UX Report API istemcisi; bir
  origin için gerçek kullanıcı Core Web Vitals değerlerini (LCP, INP, CLS,
  FCP, TTFB) döndürür, aynı `PSI_API_KEY`'i kullanır.

### Üçüncü taraf entegrasyonları (kullanıcı başına OAuth)

- `App\Services\Google\GoogleTokenService` — GSC ve GA4 çağrılarında
  kullanılan Google OAuth erişim token'ını yeniler/saklar.
- `App\Services\Gsc\GscChecker` — doğrulanmış bir mülk için Search Console
  tıklama/gösterim/TO/ortalama sıra.
- `App\Services\Ga4\Ga4Checker` — domainin seçili mülkü için Analytics 4
  oturum/organik oturum/aktif kullanıcı; `Ga4\Ga4PropertyLister` giriş
  yapmış kullanıcının erişebildiği hesapları/mülkleri listeler (hesaba göre
  gruplu, GA4 Admin API sayfalamasını izleyerek), böylece mülk elle
  yazılmak yerine açılır listeden seçilir.
- `App\Services\Bing\BingTokenService` — Bing OAuth erişim token'ını
  yeniler/saklar; `Bing\BingBacklinksChecker` Bing Webmaster Tools'tan
  backlink sayılarını ve en çok link alan sayfaları (`GetUserSites`,
  `GetLinkCounts`) ve en çok link alan 5 sayfaya link veren siteleri
  (`GetUrlLinks`) çeker.

### Backlink'ler

- `App\Services\DataForSeo\BacklinksFetcher` — DataForSEO Backlinks API
  (`summary`, `referring_domains`, `anchors` canlı uç noktaları): özet, en
  çok link veren 100 domain, anchor'lar ve son 30 günün yeni/kaybolan
  domainleri.
- `App\Services\CrawlGraph\CrawlGraphClient` — CrawlGraph API
  (`GET /api/v1/releases` ücretsiz; `POST /api/v1/backlinks` ayda 15'lik
  ücretsiz kotadan düşer, kota bitince HTTP 429).
- `App\Support\BacklinkMerger` — DataForSEO, CrawlGraph ve Bing listelerini
  kayıt edilebilir domain başına tek satırda birleştirir (alt domainler
  CrawlGraph domainine katlanır, `www.` atılır) ve her satırın kaynaklarını
  kaydeder.
- `App\Support\BacklinkOverview` — domain sayfasının Backlink sekmesi için
  veriyi hazırlar (son/önceki snapshot, trend, birleştirilmiş listeler).

## Arka plan işleri

Tüm zamanlanmış görevler için bkz. [Zamanlanmış görevler](scheduled-tasks.tr.md).

### Sitemap URL kuyruk işleri

Bir sitemap'teki her URL için Lighthouse'u (15-40 sn) veya tam sayfa içi
çekimi senkron çalıştırmak isteği/arayüzü kilitler; bu yüzden bunlar
`SitemapUrl` başına kuyruk işi olarak çalışır:

- `App\Jobs\RunSitemapUrlLighthouseCheck` — bir sitemap URL'si için
  `PageSpeedInsightsClient`'ı çalıştırır, skorları o `SitemapUrl`'e yazar.
- `App\Jobs\RunSitemapUrlOnPageCheck` — bir sitemap URL'si için
  `OnPageSeoAnalyzer`'ı çalıştırır, sonucu o `SitemapUrl`'e yazar.

Bunların işlenmesi için `worker` servisinin (`php artisan queue:work`, bkz.
`docker-compose.yml`) çalışıyor olması gerekir.

### Domain WHOIS sorgusu

`App\Jobs\RunDomainWhoisLookup` bir `Domain` için `WhoisChecker`'ı
çalıştırır ve sonucu (sorgu başarısızsa hatayı) o `Domain`'e yazar.
Yukarıdaki sitemap işleriyle aynı nedenle (WHOIS sorguları yavaş/güvenilmez
ağ çağrılarıdır) bu da `worker` servisi üzerinden çalışır. Bir domain
eklendiğinde otomatik başlatılır (`DomainController::store()`) ve domain
sayfasındaki WHOIS yenileme butonuyla (`DomainController::refreshWhois()`)
istendiğinde yeniden başlatılabilir. "Site Kontrolü Yap"ın aksine asla bir
web isteği içinde senkron çalışmaz.

### Backlink çekimleri

- `App\Jobs\FetchDomainBacklinks` — domain başına tek bir DataForSEO
  backlink çekimi (benzersiz, istekler ücretli olduğu için yeniden deneme
  yok). "Şimdi yenile" butonu ve `backlinks:run-monthly-fetch` (her ayın
  1'i, 04:00) tarafından başlatılır.
- `backlinks:sync-crawlgraph` (her gün, 05:00) — en yeni Common Crawl
  sürümünü kontrol eder ve yalnızca bu sürüm için henüz satırı olmayan
  domainleri sorgular; ilk kota hatasında durur.

## Kimlik doğrulama, erişim kontrolü ve yerelleştirme

- Google OAuth: `App\Http\Controllers\Auth\GoogleController` (Socialite);
  her Google hesabı için bir `User` kaydı oluşturur/bulur ve GSC/GA4 erişimi
  için refresh token'ını saklar. Callback adresi isteğin host'undan
  üretilir, bu yüzden giriş başladığı domaine döner; her host'un
  `/auth/google/callback` adresi Google Console'a eklenmelidir.
- Bing OAuth: `App\Http\Controllers\Auth\BingController`; Bing Webmaster
  backlink verisi için kullanıcı başına (Google girişinden sonra) bağlanır,
  kendi refresh token'ını Google'ınkinden ayrı saklar.
- `App\Http\Middleware\EnsureUserApproved` — `approved_at` boş olan (henüz
  onaylanmamış) kullanıcıları `/pending-approval`'a yönlendirir.
- `App\Http\Middleware\EnsureUserIsAdmin` — `is_admin` olmayan
  kullanıcılar için `/admin/*` rotalarını engeller (`admin` middleware
  takma adıyla).
- `App\Http\Middleware\SetLocale` — giriş yapmış kullanıcının saklanan
  `locale` değerini (ilk girişte Google hesabının dilini) isteğe uygular;
  çeviri metinleri `www/lang/en.json`, `www/lang/tr.json` ve
  `www/lang/en` / `www/lang/tr` klasörlerindedir.

Her iki middleware de `bootstrap/app.php`'de tüm `web` grubuna kayıtlıdır;
bu yüzden her istekte, rotaya özel `auth`/`admin` middleware'i erişimi
daraltmadan önce çalışır.
