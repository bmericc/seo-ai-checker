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

## Hızlı başlangıç (Docker)

```bash
docker network create npm_proxy   # tek seferlik, bkz. docs/docker.md
cp .env.docker.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan migrate
```

Panel varsayılan olarak `http://localhost:8080` adresinde çalışır. Google
girişini kurmak için [Web Arayüzü](docs/web-dashboard.md) sayfasına devam edin.

## Dokümantasyon

Detaylı dokümanlar İngilizce olarak `docs/` klasöründe tutuluyor:

- [Docker ile çalıştırma](docs/docker.md) — dev, production,
  nginx-proxy-manager arkasında ve Docker olmadan manuel kurulum.
- [Web arayüzü](docs/web-dashboard.md) — Google OAuth kurulumu, `.env`
  referansı, Docker olmadan deploy ve günlük kullanım.
- [Mimari](docs/architecture.md) — proje yapısı, AI Overview tespiti ve
  Lighthouse entegrasyonunun nasıl çalıştığı, veritabanı şeması ve temel
  servisler.
- [Kullanıcı yönetimi ve admin paneli](docs/admin.md) — kullanıcı onayı,
  admin rolleri.
- [Geliştirme](docs/development.md) — yerel kurulum ve testlerin
  çalıştırılması.

## Lisans

[GNU General Public License v3.0 veya sonrası](LICENSE) (GPL-3.0-or-later) ile lisanslanmıştır.
