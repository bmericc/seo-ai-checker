🇹🇷 Türkçe | [🇬🇧 English](scheduled-tasks.md)

[← README'ye dön](../README.tr.md)

# Zamanlanmış görevler

`scheduler` container'ı `php artisan schedule:work` çalıştırır ve aşağıdaki
görevleri zamanında başlatır. Çoğu yalnızca kuyruğa iş bırakır; işleri
`worker` container'ı çalıştırır. **İki container da çalışıyor olmalı**,
yoksa hiçbir şey otomatik çalışmaz.

Saatler **UTC**'dir (uygulamanın saat dilimi). Türkiye (UTC+3) için 3 saat
ekleyin.

| Görev | Ne zaman (UTC) | Ne yapar | Maliyet |
|---|---|---|---|
| `domains:run-daily-checks` | her gün 03:00 | Her domain için site kontrolü: robots.txt, site haritası, llms.txt, güvenlik başlıkları, CrUX, Search Console, GA4, Bing backlink'leri | ücretsiz (Google/Bing kotaları) |
| `backlinks:sync-crawlgraph` | her gün 05:00 | CrawlGraph link veren domainleri; yalnızca en yeni Common Crawl arşivi için verisi olmayan domainler | ücretsiz, ayda 15 sorgu |
| `keywords:run-weekly-checks` | pazar 02:00 | Her kelimenin tam kontrolü: SERP masaüstü + mobil, sayfa içi, Lighthouse; kelime metrikleri | tanımlıysa DataForSEO |
| `keywords:collect-queued-serp` | 5 dakikada bir | Biten DataForSEO kuyruk sonuçlarını toplar, hazır kelimelerin kontrolünü başlatır | ücretsiz |
| `reports:send-weekly` | pazartesi 08:00 | Kontrol edilmiş domaini olan her onaylı kullanıcıya haftalık SEO özeti e-postası | e-posta sunucusu |
| `backlinks:run-monthly-fetch` | her ayın 1'i 04:00 | Her domain için DataForSEO backlink verisi | domain başına yaklaşık 0,13 $ |

## Ayrıntılar

### Günlük site kontrolü

Her domain için ayrı iş; yavaş veya hata veren bir site diğerlerini
bekletmez. Bir domain yaklaşık 30–60 saniye sürer. **Site Kontrolü Yap**
butonuyla aynı işi yapar.

### Haftalık anahtar kelime kontrolü

- **DataForSEO varsa:** masaüstü ve mobil SERP görevleri, canlı isteklerden
  yaklaşık 3 kat ucuz olan **Standard kuyruğa** gider. Sonuçları
  `keywords:collect-queued-serp` 5 dakikada bir toplar; iki cihaz da hazır
  olunca kelimenin tam kontrolü kuyruğa girer.
  `SCHEDULED_SERP_QUEUE_TIMEOUT_HOURS` (varsayılan 6) sürede hazır olmayan
  görevler canlı olarak yeniden denenir.
- **DataForSEO yoksa:** her kelime doğrudan kazımayla, istekler arasında
  `REQUEST_DELAY_MS` beklenerek kontrol edilir.
- Yapay zekâ asistanı kontrolleri (kendi OpenAI/Anthropic/Gemini/Perplexity
  anahtarlarınız ve DataForSEO LLM Scraper) ücret doğurduğu için varsayılan
  olarak **çağrılmaz**. `SCHEDULED_KEYWORD_CHECKS_INCLUDE_LLM=true` ile
  açılır.

### Aylık backlink

Yalnızca DataForSEO tanımlıysa çalışır. Herhangi bir domainin backlink'leri
Backlink sekmesindeki **Şimdi yenile** ile istendiğinde de çekilebilir.

### CrawlGraph

Her gün çalışır, ama yalnızca en yeni arşiv için verisi olmayan domainler
için istek gönderir (arşivler yaklaşık üç ayda bir çıkar). Ücretsiz kota
dolunca (HTTP 429) durur; kalan domainler kota yenilenince sorgulanır.

### Haftalık e-posta

Kontrol edilmiş domaini olmayan kullanıcıya e-posta gitmez. Gerçek bir
e-posta ayarı gerekir: `MAIL_MAILER=smtp` ile `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. Varsayılan `log`
e-postayı yalnızca log dosyasına yazar.

## Görevleri kapatma

`SCHEDULED_KEYWORD_CHECKS=false` haftalık kelime kontrolünü ve aylık
backlink çekimini (ücretli görevler) kapatır. Günlük site kontrolü ve
CrawlGraph çalışmaya devam eder.

## Bir görevi elle çalıştırma

Hazır imaj:

```bash
docker compose -f docker-compose.release.yml exec app php artisan domains:run-daily-checks
```

Kaynak koddan kurulum:

```bash
docker compose exec app php artisan keywords:run-weekly-checks
```

Tüm görevleri ve bir sonraki çalışma zamanlarını listeleyin:

```bash
docker compose exec app php artisan schedule:list
```
