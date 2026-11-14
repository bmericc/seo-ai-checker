🇹🇷 Türkçe | [🇬🇧 English](CONTRIBUTING.md)

# Katkıda bulunma

Yardımınız için teşekkürler. Hata bildirimleri, fikirler ve pull request'ler
memnuniyetle karşılanır.

## Hata bildirmek veya özellik istemek

Bir [issue](https://github.com/bmericc/seo-ai-checker/issues) açın ve şunları
yazın:

- ne yaptınız, ne bekliyordunuz, ne oldu
- sürüm (imaj etiketi veya commit) ve nasıl çalıştırdığınız (hazır imaj,
  kaynak koddan derleme, Docker olmadan)
- loglardan ilgili satırlar (`docker compose logs app worker`)

Göndermeden önce API anahtarlarını, token'ları, e-postaları ve diğer özel
verileri silin. **Güvenlik sorunları** herkese açık issue ile değil,
[güvenlik politikası](SECURITY.tr.md) üzerinden bildirilir.

## Kurulum

[Docker kurulum adımları → B. Docker ile, kaynak koddan derleyerek](docs/docker-install.tr.md#b-docker-ile-kaynak-koddan-derleyerek)
veya Docker'sız kurulum için [Geliştirme](docs/development.tr.md) belgesini
izleyin. Kod yapısı: [Mimari](docs/architecture.tr.md).

## Değişiklik yapmak

1. Depoyu fork'layın ve `main`'den bir dal açın.
2. Değişikliği tek bir konuya odaklı tutun.
3. `www/tests/` altında test ekleyin veya güncelleyin.
4. Testleri çalıştırın:
   ```bash
   docker compose exec app php artisan test
   ```
5. PHP kodunu Laravel Pint ile biçimlendirin:
   ```bash
   docker compose exec app ./vendor/bin/pint
   ```
6. `main`'e karşı bir pull request açın. CI
   (`.github/workflows/tests.yml`) test paketini çalıştırır.

## Kurallar

- **Commit mesajları ve pull request'ler** İngilizce, kısa emir cümlesiyle
  yazılır ("Add backlink trend chart").
- **Kullanıcıya görünen metinler** `__()` içinde Türkçe yazılır ve
  `www/lang/en.json` içinde İngilizceye çevrilir. Bir metin eklediğinizde
  veya değiştirdiğinizde ikisini de güncelleyin. Doğru Türkçe harfleri
  kullanın (ı, ş, ğ, ü, ö, ç).
- **Kod yorumları** dosyanın mevcut stilini izler.
- **Dokümantasyon** iki dillidir: `docs/x.md`'yi değiştirdiğinizde
  `docs/x.tr.md`'yi de güncelleyin.
- Yeni ayarlar `config/seo.php` içine bir `env()` varsayılanıyla girer;
  API anahtarıysa `.env.docker.example`'a ve
  [API listesine](docs/docker-install.tr.md#kullanılabilecek-apiler) de
  eklenir.

## Lisans

Katkıda bulunarak çalışmanızın projenin
[GPL-3.0 lisansı](LICENSE) altında yayınlanmasını kabul etmiş olursunuz.
