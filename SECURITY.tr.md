🇹🇷 Türkçe | [🇬🇧 English](SECURITY.md)

# Güvenlik politikası

## Desteklenen sürümler

Güvenlik düzeltmeleri yalnızca son sürüm için yapılır. En yeni imaj
etiketini veya `main` dalını kullanın.

| Sürüm | Destekleniyor |
|---|---|
| 1.0.x | evet |

## Güvenlik açığı bildirmek

**Herkese açık issue açmayın.** GitHub üzerinden gizli olarak bildirin:
[Security → Report a vulnerability](https://github.com/bmericc/seo-ai-checker/security/advisories/new).

Lütfen şunları ekleyin:

- sorun nedir, bir saldırgan bununla ne yapabilir
- yeniden üretme adımları veya kavram kanıtı
- sürüm (imaj etiketi veya commit)

7 gün içinde ilk yanıtı almanız beklenir. Düzeltme yayınlandığında
bildirim herkese açılır ve aksini istemezseniz adınız anılır.

## Kapsam

Kapsamda: bu depodaki uygulama kodu ve yayınlanan Docker imajı.

Kapsam dışı: üçüncü taraf servislerdeki sorunlar (Google, DataForSEO,
OpenAI ve diğerleri), yanlış yapılandırılmış kendi kurulumlarınız (örneğin
production'da `APP_DEBUG=true` veya sızdırılmış bir `APP_KEY`) ve yoğun
trafikle hizmet engelleme.

## Güvenli çalıştırma

- Production'da `APP_DEBUG=false` tutun ve uygulamayı HTTPS ile sunun.
- `APP_KEY`'i ve `.env`'yi gizli tutun; `APP_KEY`'e sahip olan herkes
  saklanan OAuth token'larını ve API anahtarlarını çözebilir.
- Yalnızca `ADMIN_EMAILS` içindeki hesaplar otomatik yönetici olur; diğer
  kullanıcıları yalnızca tanıyorsanız onaylayın.
- Yeni imaj etiketleri yayınlandığında güncelleyin.
