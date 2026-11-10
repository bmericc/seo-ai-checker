🇹🇷 Türkçe | [🇬🇧 English](MCP.md)

# MCP sunucusu ve ajan becerileri

SEO AI Checker bir Model Context Protocol (MCP) sunucusu sunar. Claude, ChatGPT, Cursor ve Claude Code gibi asistanlar domain verilerinizi okuyabilir ve işlem başlatabilir.

Sunucu adresi: `https://seo-checker.tarti.com/mcp`

## Bağlanma

Token kopyalamanız gerekmez. Bağlantı OAuth ile kurulur ve giriş sistemdeki Google hesabınızla yapılır.

1. Asistanınıza sunucu adresini ekleyin:
   - **Claude (claude.ai / masaüstü):** Ayarlar → Bağlayıcılar → Özel bağlayıcı ekle → adres `https://seo-checker.tarti.com/mcp`.
   - **ChatGPT:** Ayarlar → Bağlayıcılar → Oluştur → aynı adres, kimlik doğrulama "OAuth".
   - **Claude Code:**

     ```bash
     claude mcp add --transport http seo-ai-checker https://seo-checker.tarti.com/mcp
     ```

     Ardından Claude Code içinde `/mcp` komutuyla bağlantıyı yetkilendirin.
   - **Cursor (`mcp.json`):**

     ```json
     {
       "mcpServers": {
         "seo-ai-checker": { "url": "https://seo-checker.tarti.com/mcp" }
       }
     }
     ```

2. Asistan tarayıcıda onay sayfasını açar. Oturum açık değilse doğrudan Google girişine yönlendirilirsiniz.
3. İstenen izinleri görüp **İzin ver**'e basın.

Verdiğiniz erişimleri **Bağlantılar → Yapay zekâ asistanları (MCP)** kartında görebilir ve istediğiniz zaman kaldırabilirsiniz.

## Yetkiler

- Her üye yalnızca kendi domainlerini görür. Yönetici tüm üyelerin domainlerini görür.
- Onayı bekleyen (henüz onaylanmamış) hesaplar hiçbir araca erişemez.
- İki izin (scope) vardır:
  - `read`: okuma araçları.
  - `write`: ekleme, kontrol başlatma, IndexNow gönderimi ve rapor linki.
- İzni olmayan araçlar listede de görünmez.
- MCP üzerinden hiçbir şey silinemez.
- `explore_prompt` ve `get_prompt_exploration` yalnızca yöneticilere açıktır.

## Araçlar

| Araç | İzin | Ne yapar |
| --- | --- | --- |
| `list_domains` | read | Domainler, sağlık skoru, kelime sayısı, AI Overview atıf oranı |
| `get_domain` | read | Sağlık skoru, öncelikli sorunlar ve çözümleri, kontroller, domain yaşı |
| `list_keywords` | read | Kelimeler: Google sırası, AI Overview, Lighthouse |
| `get_indexnow_status` | read | IndexNow anahtar dosyası, doğrulama durumu, son gönderimler |
| `add_domain` | write | Domain ekler, ilk site kontrolünü arka planda başlatır |
| `add_keyword` | write | Domaine kelime ekler |
| `run_domain_check` | write | Site kontrolünü arka planda yeniden çalıştırır |
| `run_keyword_check` | write | Kelime kontrolünü arka planda çalıştırır (1-3 dk) |
| `submit_indexnow` | write | URL'leri ya da sitemap'i IndexNow'a bildirir |
| `create_report_link` | write | Giriş gerektirmeyen paylaşılabilir rapor linki |
| `explore_prompt` | write, yönetici | Bir soruyu ChatGPT, Claude, Gemini ve Perplexity'ye sorar |
| `get_prompt_exploration` | read, yönetici | `explore_prompt` cevaplarını ve anılan siteleri döner |

Kontroller kuyrukta çalışır. Araç hemen döner; sonucu bir iki dakika sonra okuma araçlarıyla alırsınız.

## Ajan becerileri

`skills/` klasöründe hazır iş akışları var. Claude Code'da kullanmak için klasörleri `~/.claude/skills/` (ya da projenin `.claude/skills/`) altına kopyalayın:

- `seo-site-audit`: öncelikli düzeltme planı
- `keyword-rank-report`: sıralama ve AI Overview raporu
- `ai-visibility-research`: yapay zekâ asistanlarında marka görünürlüğü (yönetici)
- `client-report`: müşteriye gönderilecek özet ve rapor linki
- `indexnow-publish`: yeni sayfaları IndexNow ile bildirme

## Teknik notlar

- Sunucu [laravel/mcp](https://github.com/laravel/mcp), OAuth ise [laravel/passport](https://github.com/laravel/passport) ile çalışır.
- Uç nokta: `POST /mcp`, Streamable HTTP. `GET` ve `DELETE` 405 döner.
- Keşif adresleri:
  - `/.well-known/oauth-protected-resource/mcp`
  - `/.well-known/oauth-authorization-server`
- Dinamik istemci kaydı: `POST /oauth/register`. Yetkilendirme `/oauth/authorize`, token `/oauth/token` adresindedir. PKCE (S256) zorunludur.
- Token süreleri: erişim token'ı 15 gün, yenileme token'ı 90 gün.
- İstek sınırı: kullanıcı başına dakikada 120 istek.
- Kurulum ve deploy:
  - `composer install`, `php artisan migrate` ve `php artisan passport:keys` çalıştırın.
  - Anahtar dosyaları `storage/oauth-*.key` php-fpm kullanıcısına ait olmalı (Docker'da `www-data`, izin `600`).
