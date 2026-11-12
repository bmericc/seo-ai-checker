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

- Her üye kendi domainlerini ve domain sayfasındaki "Erişim" penceresinden kendisiyle paylaşılanları görür. `list_domains` her domain için `access` seviyenizi döner (`owner`, `write`, `read`); salt okunur paylaşımlarda yazma araçları hata verir. Yönetici tüm üyelerin domainlerini görür.
- Onayı bekleyen (henüz onaylanmamış) hesaplar hiçbir araca erişemez.
- İki izin (scope) vardır:
  - `read`: okuma araçları.
  - `write`: ekleme, kontrol başlatma, IndexNow gönderimi ve rapor linki.
- İzni olmayan araçlar listede de görünmez.
- MCP üzerinden hiçbir şey silinemez.
- `explore_prompt` yalnızca yöneticilere açıktır (domain'in yapay zekâ API anahtarlarını kullanır).

## Araçlar

| Araç | İzin | Ne yapar |
| --- | --- | --- |
| `list_domains` | read | Domainler, sağlık skoru, kelime sayısı, AI Overview atıf oranı |
| `get_domain` | read | Sağlık skoru, öncelikli sorunlar ve çözümleri, kontroller, domain yaşı |
| `list_keywords` | read | Kelimeler: Google sırası, AI Overview (son 10 kontroldeki istikrar dahil), Lighthouse |
| `get_visibility_changes` | read | Son N günde neyin kötüleştiği veya iyileştiği (sıralama, AI Overview kaynağı, asistan anılmaları, site kontrolleri), etki sırasına göre |
| `get_competitors` | read | SERP rakipleri ve Share of Model: her yapay zekâ asistanının siteyi ve rakipleri ne sıklıkla andığı |
| `generate_llms_txt` | read | Sitemap sayfalarından llms.txt taslağı (hiçbir şey yayınlanmaz) |
| `get_indexnow_status` | read | IndexNow anahtar dosyası, doğrulama durumu, son gönderimler |
| `add_domain` | write | Domain ekler, ilk site kontrolünü arka planda başlatır |
| `add_keyword` | write | Domaine kelime ekler |
| `run_domain_check` | write | Site kontrolünü arka planda yeniden çalıştırır |
| `run_keyword_check` | write | Kelime kontrolünü arka planda çalıştırır (1-3 dk) |
| `submit_indexnow` | write | URL'leri ya da sitemap'i IndexNow'a bildirir |
| `create_report_link` | write | Giriş gerektirmeyen paylaşılabilir rapor linki |
| `explore_prompt` | write, yönetici | Bir soruyu ChatGPT, Claude, Gemini ve Perplexity'ye sorar |
| `get_prompt_exploration` | read | Kayıtlı bir yapay zekâ kontrolünün tamamını döner: cevaplar, anılma durumu, anılan siteler |
| `check_ai_answer` | write | Takip edilen bir kelimenin manuel kontrolünü kaydeder: asistanın kendi cevapları, domain'in geçip geçmediği, anılan diğer siteler |
| `list_ai_checks` | read | Kayıtlı yapay zekâ kontrollerinin geçmişi (manuel ve API); anahtar kelimeye, asistana, soru metnine ve kaynağa göre süzülebilir |
| `get_ai_check_prompts` | read (kelime eklemek için write) | Manuel kontrol soruları API formatında; yeni kelimeyi önce ekler ya da takip edilen tüm kelimeleri listeler |

### Manuel yapay zekâ kontrolü (`check_ai_answer`)

Asistanınız kelimelerinizi API anahtarı kullanmadan, API kontrolleriyle aynı formatta kendisi kontrol eder: aynı talimat, aynı soru (*"… konusunda hangi web sitelerine bakmalıyım?"*) ve Türkiye'deki sitelerle ilgili aynı takip sorusu. Serbest sorular manuel olarak kontrol edilmez.

1. Asistanınıza örneğin şunu söyleyin: *"example.com'un kelimelerini kontrol et"* ya da *"example.com için 'e-fatura' kelimesini kontrol et"*.
2. Asistan `get_ai_check_prompts` aracını çağırır. Domain'de takip edilmeyen bir kelime verildiyse kelime önce kelimelere eklenir. Kelime verilmezse takip edilen tüm kelimeler döner.
3. Asistan her soruyu ve takip sorusunu cevaplar, sonra `check_ai_answer` ile kaydeder. API kontrollerinde olduğu gibi asistan adı ve tam model adı (örneğin `grok` / `grok-5`) zorunludur. Bu araç yalnızca domain'de zaten takip edilen kelimeleri kabul eder.

Araç domain'in cevaplarda ya da kaynak URL'lerde geçip geçmediğini söyler ve anılan diğer siteleri listeler. Claude, ChatGPT, Gemini ve Perplexity API kontrolleriyle aynı adla, diğer asistanlar (Grok, DeepSeek, Copilot…) kendi adlarıyla kaydedilir.

Her sonuç geçmiş olarak saklanır. Üyeler bunu kelime sayfasında ve domain sayfasında **Yapay zekâ görünürlüğü → Yapay zekâ kontrol geçmişi** altında "Manuel (MCP)" etiketiyle görür; `list_ai_checks` de aynı geçmişi döner.

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
