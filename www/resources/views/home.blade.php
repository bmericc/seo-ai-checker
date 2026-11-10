<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — {{ __('Google ve yapay zekâ aramasında görünürlük takibi') }}</title>
    <meta name="description" content="{{ __("Sıralamalarınızı, Google AI Overview atıflarınızı ve ChatGPT, Claude, Gemini, Perplexity'nin sizi önerip önermediğini takip edin. Sorunları önem sırasına göre ve nasıl düzelteceğinizle birlikte görün.") }}">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.45.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v=1">
</head>
<body>
<header class="l-header">
    <div class="l-wrap">
        <a href="{{ route('dashboard') }}" class="l-brand">
            <span class="l-logo" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
            <span class="l-brand-name">{{ config('app.name') }}</span>
        </a>
        <nav class="l-nav" aria-label="{{ __('Ana menü') }}">
            <a href="#ozellikler" class="l-nav-link">{{ __('Neleri ölçer') }}</a>
            <a href="#nasil" class="l-nav-link">{{ __('Nasıl çalışır') }}</a>
            <a href="#erisim" class="l-nav-link">{{ __('Erişim') }}</a>
            <a href="{{ route('google.redirect') }}" class="l-btn l-btn-outline">{{ __('Giriş yap') }}</a>
        </nav>
    </div>
</header>

<main>
    @if (session('flash'))
        <div class="l-wrap">
            <div class="l-flash l-flash-{{ session('flash')['type'] === 'error' ? 'error' : 'success' }}" role="alert">
                {{ session('flash')['message'] }}
            </div>
        </div>
    @endif

    <section class="l-wrap l-hero">
        <div class="l-hero-copy">
            <span class="l-badge">{{ __('Klasik SEO + yapay zekâ araması, tek panelde') }}</span>
            <h1>{!! __('Siteniz <span class="l-hl-blue">Google\'da</span> ve <span class="l-hl-green">yapay zekâ</span> yanıtlarında nerede duruyor?') !!}</h1>
            <p class="l-lead">{{ __("Sıralamalarınızı, Google AI Overview atıflarınızı ve ChatGPT, Claude, Gemini, Perplexity'nin sizi önerip önermediğini takip edin. Sorunları önem sırasına göre ve nasıl düzelteceğinizle birlikte görün.") }}</p>
            <div class="l-actions">
                <a href="{{ route('google.redirect') }}" class="l-btn l-btn-primary">
                    <i class="ti ti-brand-google" aria-hidden="true"></i>{{ __('Google ile giriş yap') }}
                </a>
                <a href="#ozellikler" class="l-btn l-btn-ghost">{{ __('Neleri ölçtüğüne bak') }}</a>
            </div>
            <p class="l-note">{{ __('Davetle çalışır: yalnızca önceden izin verilmiş Google hesapları giriş yapabilir.') }}</p>
        </div>

        <div class="l-hero-visual">
            <div class="l-card" role="img" aria-label="{{ __('Örnek domain paneli: sağlık skoru, öncelikli sorunlar ve yapay zekâ görünürlüğü') }}">
                <div class="l-card-head">
                    <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                        <span class="l-eyebrow">{{ __('Örnek görünüm') }}</span>
                        <span class="l-card-domain">ornekmagaza.com</span>
                    </div>
                    <div class="l-score">
                        <div class="l-ring">76</div>
                        <div class="l-score-text"><strong>{{ __('Site sağlığı') }}</strong><span>{{ __('2 konu dikkat istiyor') }}</span></div>
                    </div>
                </div>

                <div class="l-issues">
                    <div class="l-issue l-issue-high">
                        <span class="l-sev">{{ __('Yüksek') }}</span>
                        <div class="l-issue-body">
                            <span class="l-issue-title">{{ __('2 yapay zekâ botu sitenizi okuyamıyor') }}</span>
                            <span class="l-issue-desc">{{ __("robots.txt GPTBot ve ClaudeBot'u engelliyor. Çözüm: ilgili Disallow satırlarını kaldırın.") }}</span>
                        </div>
                    </div>
                    <div class="l-issue">
                        <span class="l-sev">{{ __('Düşük') }}</span>
                        <div class="l-issue-body">
                            <span class="l-issue-title">{{ __('llms.txt dosyası yok') }}</span>
                            <span class="l-issue-desc">{{ __('Asistanlara sitenizi tanıtan kısa bir özet dosyası ekleyin.') }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="l-assist-label">{{ __('":query" sorusunda sizi önerenler', ['query' => __('istanbul el yapımı seramik')]) }}</span>
                    <div class="l-assist">
                        <div><span class="l-assist-name">ChatGPT</span><span class="l-assist-val l-ok">{{ __('Anıldı') }}</span></div>
                        <div><span class="l-assist-name">Claude</span><span class="l-assist-val l-ok">{{ __('Anıldı') }}</span></div>
                        <div><span class="l-assist-name">Gemini</span><span class="l-assist-val l-no">{{ __('Yok') }}</span></div>
                        <div><span class="l-assist-name">Perplexity</span><span class="l-assist-val l-src">{{ __('Kaynak') }}</span></div>
                    </div>
                </div>

                <div class="l-stats">
                    <div><span class="l-stat-label">{{ __('Google sırası') }}</span><span class="l-stat-val">4.</span></div>
                    <div><span class="l-stat-label">AI Overview</span><span class="l-stat-val">{{ __('Kaynak') }}</span></div>
                    <div><span class="l-stat-label">{{ __('Performans') }}</span><span class="l-stat-val">68</span></div>
                </div>
            </div>
        </div>
    </section>

    <section id="ozellikler" class="l-band">
        <div class="l-wrap l-section">
            <div class="l-section-head">
                <h2 class="l-h2">{{ __('Neleri ölçer') }}</h2>
                <p class="l-section-lead">{{ __('Klasik arama sonuçları ile yapay zekâ yanıtlarını aynı yerde izlersiniz. Her bulgu bir sonraki adımla birlikte gelir.') }}</p>
            </div>
            <div class="l-features">
                <div class="l-feature">
                    <span class="l-icon l-icon-blue"><i class="ti ti-list-numbers" aria-hidden="true"></i></span>
                    <h3>{{ __('Google sıralaması') }}</h3>
                    <p>{{ __('Takip ettiğiniz kelimelerde ilk ~20 organik sonucu çeker, sitenizin kaçıncı sırada çıktığını gün gün kaydeder.') }}</p>
                </div>
                <div class="l-feature">
                    <span class="l-icon l-icon-red"><i class="ti ti-sparkles" aria-hidden="true"></i></span>
                    <h3>{{ __('Google AI Overview') }}</h3>
                    <p>{{ __('Sonuç sayfasında AI Overview kutusu var mı, hangi siteler kaynak gösteriliyor, siz aralarında mısınız.') }}</p>
                </div>
                <div class="l-feature">
                    <span class="l-icon l-icon-yellow"><i class="ti ti-message-chatbot" aria-hidden="true"></i></span>
                    <h3>{{ __('Yapay zekâ asistanları') }}</h3>
                    <p>{{ __("Gerçek bir müşterinin sorusunu ChatGPT, Claude, Gemini ve Perplexity'ye sorar; sizi anan, kaynak gösteren ve rakip olarak önerilen siteleri listeler.") }}</p>
                </div>
                <div class="l-feature">
                    <span class="l-icon l-icon-green"><i class="ti ti-heartbeat" aria-hidden="true"></i></span>
                    <h3>{{ __('Site sağlığı') }}</h3>
                    <p>{{ __("robots.txt'te yapay zekâ botları, site haritası, HTTPS ve güvenlik başlıkları, llms.txt. Sorunlar etkisine göre sıralanır.") }}</p>
                </div>
                <div class="l-feature">
                    <span class="l-icon l-icon-blue"><i class="ti ti-gauge" aria-hidden="true"></i></span>
                    <h3>{{ __('Hız ve sayfa kalitesi') }}</h3>
                    <p>{{ __('Lighthouse skorları, gerçek kullanıcı hız verisi (CrUX) ve sayfa başına on-page SEO: başlıklar, meta, görseller, schema, hreflang.') }}</p>
                </div>
                <div class="l-feature">
                    <span class="l-icon l-icon-red"><i class="ti ti-plug-connected" aria-hidden="true"></i></span>
                    <h3>{{ __('Kendi verileriniz') }}</h3>
                    <p>{{ __('Search Console, GA4 ve Bing Webmaster hesaplarınızı bağlayın; tıklama, ziyaretçi ve backlink verisi aynı panele gelir.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section id="nasil" class="l-wrap l-section">
        <h2 class="l-h2">{{ __('Nasıl çalışır') }}</h2>
        <div class="l-steps">
            <div class="l-step">
                <span class="l-step-num">01</span>
                <h3>{{ __('Domaininizi ekleyin') }}</h3>
                <p>{{ __('Site adresini yazın. İlk site kontrolü hemen başlar; birkaç dakika içinde sağlık skorunu ve öncelikli sorunları görürsünüz.') }}</p>
            </div>
            <div class="l-step">
                <span class="l-step-num">02</span>
                <h3>{{ __('Kelimelerinizi takip edin') }}</h3>
                <p>{{ __('Müşterilerinizin aradığı ifadeleri ekleyin. Sıralama, AI Overview atfı ve sayfa skorları her kontrolde kaydedilir.') }}</p>
            </div>
            <div class="l-step">
                <span class="l-step-num">03</span>
                <h3>{{ __('Düzeltin ve paylaşın') }}</h3>
                <p>{{ __('Önerilen düzeltmeleri yapın, yeni sayfaları IndexNow ile bildirin, müşterinize giriş gerektirmeyen bir rapor linki gönderin.') }}</p>
            </div>
        </div>
    </section>

    <section class="l-wrap l-mcp-outer">
        <div class="l-mcp">
            <div class="l-mcp-copy">
                <span class="l-eyebrow">{{ __('Asistanınızla kullanın') }}</span>
                <h2>{{ __('Claude veya ChatGPT\'ye "sitemde ne sorun var?" diye sorun') }}</h2>
                <p>{{ __('Panel bir MCP sunucusu da sunar. Asistanınızı Google hesabınızla bağlayın; yalnızca sizin domainlerinizi görür, hiçbir şeyi silemez.') }}</p>
            </div>
            <div class="l-mcp-prompts">
                <div class="l-prompt">“{{ __('ornekmagaza.com için öncelikli düzeltme planı çıkar.') }}”</div>
                <div class="l-prompt">“{{ __('Hangi kelimelerde AI Overview var ama biz kaynak değiliz?') }}”</div>
                <div class="l-prompt">“{{ __('Müşterime 30 gün geçerli bir rapor linki hazırla.') }}”</div>
            </div>
        </div>
    </section>

    <section id="erisim" class="l-wrap l-access-outer">
        <div class="l-access">
            <h2>{{ __('Erişiminiz var mı?') }}</h2>
            <p>{{ __('Bu panel herkese açık değildir. Yalnızca site sahibinin önceden izin verdiği Google hesaplarıyla giriş yapılabilir. Erişim için panel yöneticisine başvurun.') }}</p>
            <a href="{{ route('google.redirect') }}" class="l-btn l-btn-primary">
                <i class="ti ti-brand-google" aria-hidden="true"></i>{{ __('Google ile giriş yap') }}
            </a>
        </div>
    </section>
</main>

<footer class="l-footer">
    <div class="l-wrap">
        <span>{{ config('app.name') }}</span>
        <nav aria-label="{{ __('Yasal') }}">
            <a href="{{ route('privacy-policy') }}">{{ __('Gizlilik Politikası') }}</a>
            <a href="{{ route('terms-of-service') }}">{{ __('Kullanım Koşulları') }}</a>
        </nav>
    </div>
</footer>
</body>
</html>
