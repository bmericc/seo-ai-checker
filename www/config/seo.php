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
    | Erisim kontrolu
    |--------------------------------------------------------------------------
    | Panele giris yapabilecek Google e-postalari. Bos birakilirsa hic kimse
    | giris yapamaz (varsayilan olarak erisim kapalidir).
    */
    'allowed_google_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ALLOWED_GOOGLE_EMAILS', ''))
    ))),

];
