<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google SERP scraping ayarlari
    |--------------------------------------------------------------------------
    */
    'serp' => [
        'hl' => env('GOOGLE_HL', 'tr'),
        'gl' => env('GOOGLE_GL', 'tr'),
        'request_delay_ms' => (int) env('REQUEST_DELAY_MS', 4000),
        'proxy' => env('HTTP_PROXY'),
        'user_agent' => env('SCRAPER_USER_AGENT'),
        // auto | google | dataforseo | fallback - bkz. AppServiceProvider,
        // FallbackSerpScraper. fallback: once ucretsiz dogrudan kazima,
        // engellenirse ayni sorgu DataForSEO'ya gider (mobil SERP atlanir,
        // haftalik kontrol Standard kuyruk yerine live calisir).
        'provider' => env('SERP_PROVIDER', 'auto'),
        // fallback modunda Google engellediginde bu sure boyunca Google hic
        // denenmez, sorgular dogrudan DataForSEO'ya gider. 0 = kapali.
        'fallback_cooldown_minutes' => (int) env('SERP_FALLBACK_COOLDOWN_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | DataForSEO SERP API (opsiyonel)
    |--------------------------------------------------------------------------
    | Google'i dogrudan kazimak "unusual traffic"/CAPTCHA engellemesine
    | siklikla takiliyor - DATAFORSEO_LOGIN/DATAFORSEO_PASSWORD tanimlanirsa
    | SERP sorgulari bunun yerine DataForSEO'nun ucretli Live Advanced Google
    | Organic API'sine gider (bkz. AppServiceProvider, DataForSeoSerpScraper).
    | Tanimlanmazsa uygulama otomatik olarak dogrudan kazima (GoogleSerpScraper)
    | yontemine geri doner.
    */
    'dataforseo' => [
        'login' => env('DATAFORSEO_LOGIN'),
        'password' => env('DATAFORSEO_PASSWORD'),
        // 2792 = Turkiye (ulke geneli) - bkz. GET /v3/serp/google/locations/tr.
        // DataForSEO "Turkey" degil "Turkiye" ismini kullaniyor; sayisal kod
        // isim degisikliklerinden bagimsiz oldugu icin ismi degil kodu kullaniyoruz.
        'location_code' => (int) env('DATAFORSEO_LOCATION_CODE', 2792),
        // Keyword metrikleri, LLM Scraper ve LLM Mentions icin dil kodu.
        'language_code' => env('DATAFORSEO_LANGUAGE_CODE', env('GOOGLE_HL', 'tr')),
    ],

    // CrawlGraph: Common Crawl hiperlink grafiginden link veren domain'ler
    // (bkz. App\Services\CrawlGraph\CrawlGraphClient). Ucretsiz katman
    // ayda 15 backlink istegi verir; veri uc ayda bir yenilendigi icin her
    // domain yalnizca yeni bir arsiv ciktiginda bir kez sorgulanir.
    'crawlgraph' => [
        'api_key' => env('CRAWLGRAPH_API_KEY'),
        'limit' => (int) env('CRAWLGRAPH_LIMIT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Haftalik otomatik anahtar kelime kontrolu
    |--------------------------------------------------------------------------
    | Her hafta tum anahtar kelimeler icin tam kontrol (SERP desktop+mobil,
    | on-page, Lighthouse) calistirilir. DataForSEO tanimliysa SERP sorgulari
    | live yerine ~3 kat ucuz Standard kuyruga (task_post + task_get) gider
    | (bkz. RunWeeklyKeywordChecks, CollectQueuedSerpResults). AI gorunurluk
    | saglayicilari (kullanicinin kendi API key'leri ve DataForSEO LLM
    | Scraper) varsayilan olarak cagrilmaz - include_llm ile acilabilir.
    */
    'scheduled_checks' => [
        'enabled' => (bool) env('SCHEDULED_KEYWORD_CHECKS', true),
        'include_llm' => (bool) env('SCHEDULED_KEYWORD_CHECKS_INCLUDE_LLM', false),
        // Bu sureden uzun hazir olmayan kuyruk gorevleri basarisiz sayilir
        // ve kelime live SERP ile kontrol edilir.
        'queue_timeout_hours' => (int) env('SCHEDULED_SERP_QUEUE_TIMEOUT_HOURS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI gorunurluk kontrolu (ChatGPT/Claude/Gemini) - opsiyonel
    |--------------------------------------------------------------------------
    | API key'ler burada degil, domain basina domain_llm_api_keys tablosunda
    | tutulur (bkz. DomainLlmApiKey, admin paneli). Burada yalnizca hangi
    | modelin kullanilacagi ayarlanir - saglayicilarin ucuz/hafif modelleri
    | varsayilan; fiyatlar zamanla degistigi icin env ile override edilebilir.
    */
    'llm_visibility' => [
        'openai_model' => env('OPENAI_VISIBILITY_MODEL', 'gpt-5.6-luna'),
        'anthropic_model' => env('ANTHROPIC_VISIBILITY_MODEL', 'claude-haiku-5-5'),
        'gemini_model' => env('GEMINI_VISIBILITY_MODEL', 'gemini-3.8-flash'),
        'perplexity_model' => env('PERPLEXITY_VISIBILITY_MODEL', 'sonar'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Overview tespiti
    |--------------------------------------------------------------------------
    | Google SERP HTML'inde AI Overview kutusunun varligini tespit etmek icin
    | aranan metin ifadeleri (kucuk harfe cevrilerek karsilastirilir). Google
    | bu metinleri diline ve zaman icinde degistirebilir; yeni bir ifade fark
    | ederseniz bu listeye ekleyebilirsiniz.
    */
    'ai_overview_markers' => [
        'ai overview',
        'ai-overview',
        'generative ai overview',
        'yapay zeka genel bakışı',
        'yapay zekaya genel bakış',
        'yapay zeka özeti',
    ],

    /*
    | Google'in SERP markup'i siklikla degistigi ve rastgele/obfuske class
    | isimleri kullandigi icin buradaki liste kasitli olarak bostur. Kendi
    | gozlemlerinize gore tespit ettiginiz guvenilir CSS seciciler varsa
    | buraya ekleyebilirsiniz, ornek: '[data-attrid*="AiOverview"]'.
    */
    'ai_overview_selectors' => [],

    /*
    |--------------------------------------------------------------------------
    | Lighthouse (Google PageSpeed Insights API)
    |--------------------------------------------------------------------------
    */
    'lighthouse' => [
        'psi_api_key' => env('PSI_API_KEY'),
        'psi_strategy' => env('PSI_STRATEGY', 'mobile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chrome UX Report (CrUX) - gercek kullanici Core Web Vitals verisi
    |--------------------------------------------------------------------------
    | PSI_API_KEY ile ayni Google Cloud projesinde "Chrome UX Report API"
    | etkinlestirilmisse ayni anahtar burada da kullanilabilir; ayri bir
    | anahtar tanimlamak isterseniz CRUX_API_KEY ile ayarlayin.
    */
    'crux' => [
        'api_key' => env('CRUX_API_KEY', env('PSI_API_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Erisim kontrolu
    |--------------------------------------------------------------------------
    | Herkes Google ile ya da e-posta + sifreyle kayit olabilir, ancak yeni
    | hesaplar admin onaylayana kadar panele erisemez (bkz. EnsureUserApproved
    | middleware'i ve /admin/users paneli). Buradaki e-postalar ilk giris
    | yaptiklarinda OTOMATIK olarak admin + onayli sayilir - yeni bir sunucu
    | kurulumunda kendinize erisim acmak icin kullanilir; sonrasinda kullanici
    | yonetimi /admin/users uzerinden yapilir. Sifreyle kayit olanlar icin bu
    | yalnizca e-posta dogrulandiktan sonra uygulanir.
    */
    /*
    | Giris yontemleri. E-posta + sifre ile giris/kayit her zaman aciktir;
    | "Google ile giris yap" GOOGLE_LOGIN_ENABLED=false ile kapatilabilir.
    | Kapaliyken de giris yapmis kullanicilar Search Console/GA4 icin Google
    | hesaplarini baglayabilir (GOOGLE_CLIENT_ID tanimliysa).
    */
    'google_login_enabled' => (bool) env('GOOGLE_LOGIN_ENABLED', true),

    'bootstrap_admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ADMIN_EMAILS', ''))
    ))),

];
