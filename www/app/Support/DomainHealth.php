<?php

namespace App\Support;

use App\Models\Domain;

/**
 * Domain'in ortak (DomainFact) ve kayda ozel (DomainCheck) kontrol
 * sonuclarini, arayuzde gosterilecek sade bir "saglik" ozetine cevirir:
 * 0-100 arasi bir skor ve etkisine gore siralanmis konu listesi.
 *
 * Yalnizca sunum katmani icindir - hicbir checker'i calistirmaz, sadece
 * mevcut JSON kolonlarini okur. GSC/GA4/Bing gibi hesap baglantisina bagli
 * kaynaklar skoru etkilemez (baglanmamis olmak sitenin bir sorunu degil).
 */
class DomainHealth
{
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_LOW = 'low';

    private const PENALTIES = [
        self::SEVERITY_HIGH => 25,
        self::SEVERITY_MEDIUM => 12,
        self::SEVERITY_LOW => 5,
    ];

    private const SEVERITY_ORDER = [
        self::SEVERITY_HIGH => 0,
        self::SEVERITY_MEDIUM => 1,
        self::SEVERITY_LOW => 2,
    ];

    /**
     * @param  array<int, array{key: string, status: string, label: string, detail: string}>  $checks
     * @param  array<int, array{key: string, severity: string, title: string, description: string, fix: ?string}>  $issues
     */
    private function __construct(
        public readonly bool $hasData,
        public readonly ?int $score,
        public readonly array $checks,
        public readonly array $issues,
    ) {
    }

    public static function for(Domain $domain): self
    {
        $fact = $domain->fact;
        $domainCheck = $domain->latestDomainCheck;

        if (!$fact?->checked_at && !$domainCheck) {
            return new self(false, null, [], []);
        }

        $checks = [];
        $issues = [];

        // AI crawler erisimi (robots.txt)
        $aiCrawlers = $fact?->ai_crawlers ?? [];
        if ($aiCrawlers !== []) {
            $blocked = collect($aiCrawlers['crawlers'] ?? [])->filter(fn ($c) => !($c['allowed'] ?? true));
            if (($aiCrawlers['found'] ?? false) && $blocked->isNotEmpty()) {
                $checks[] = self::check('ai_crawlers', 'fail', __('Yapay zekâ botları'), __(':count bot engelli', ['count' => $blocked->count()]));
                $issues[] = self::issue(
                    'ai_crawlers',
                    self::SEVERITY_HIGH,
                    __(':count yapay zekâ botu sitenizi okuyamıyor', ['count' => $blocked->count()]),
                    __('robots.txt dosyanız şu botları engelliyor: :bots. Bu asistanlar içeriğinizi yanıtlarında kaynak gösteremez.', ['bots' => $blocked->keys()->implode(', ')]),
                    __('robots.txt dosyasındaki ilgili "Disallow: /" satırlarını kaldırın veya "Allow: /" yapın, sonra yeniden kontrol edin.'),
                );
            } else {
                $checks[] = self::check('ai_crawlers', 'ok', __('Yapay zekâ botları'), __('Hepsi erişebiliyor'));
            }
        }

        // Sitemap (kayda ozel)
        $sitemap = $domainCheck?->sitemap ?? null;
        if ($sitemap !== null) {
            if (!($sitemap['found'] ?? false)) {
                $checks[] = self::check('sitemap', 'fail', __('Site haritası'), __('Bulunamadı'));
                $issues[] = self::issue(
                    'sitemap',
                    self::SEVERITY_HIGH,
                    __('Site haritası bulunamadı'),
                    __('Ne /sitemap.xml adresinde ne de robots.txt içinde bir site haritası var. Google yeni sayfalarınızı daha geç keşfeder.'),
                    __('Bir sitemap.xml oluşturun (WordPress\'te Yoast veya Rank Math bunu otomatik yapar) ve robots.txt\'ye "Sitemap: https://…/sitemap.xml" satırını ekleyin.'),
                );
            } elseif (!($sitemap['is_valid_xml'] ?? false)) {
                $checks[] = self::check('sitemap', 'warn', __('Site haritası'), __('Geçersiz XML'));
                $issues[] = self::issue(
                    'sitemap',
                    self::SEVERITY_MEDIUM,
                    __('Site haritası okunamıyor'),
                    __('sitemap.xml bulundu ama geçerli bir XML değil; arama motorları içindeki adresleri okuyamaz.'),
                    __('Dosyayı bir XML doğrulayıcıyla kontrol edin veya site haritası eklentinizle yeniden oluşturun.'),
                );
            } else {
                $checks[] = self::check('sitemap', 'ok', __('Site haritası'), __('Geçerli'));
            }
        }

        // HTTPS + guvenlik header'lari
        $security = $fact?->security_headers ?? [];
        if ($security !== []) {
            if (!($security['reachable'] ?? true)) {
                $checks[] = self::check('security', 'fail', __('Güvenlik'), __('Siteye erişilemedi'));
                $issues[] = self::issue(
                    'security',
                    self::SEVERITY_HIGH,
                    __('Sitenize erişilemedi'),
                    __('Kontrol sırasında siteniz yanıt vermedi. Ziyaretçiler ve arama motorları da aynı sorunu yaşıyor olabilir.'),
                    __('Sunucunuzun ve alan adı yönlendirmenizin çalıştığından emin olun, sonra yeniden kontrol edin.'),
                );
            } elseif (!($security['http_redirects_to_https'] ?? false)) {
                $checks[] = self::check('security', 'fail', __('Güvenlik'), __('HTTPS yönlendirmesi yok'));
                $issues[] = self::issue(
                    'https',
                    self::SEVERITY_HIGH,
                    __('Site HTTPS\'e yönlendirmiyor'),
                    __('http:// ile gelen ziyaretçiler güvenli sürüme aktarılmıyor. Tarayıcılar bunu "güvenli değil" diye işaretleyebilir.'),
                    __('Sunucu veya hosting panelinizden HTTP → HTTPS kalıcı (301) yönlendirmesini açın.'),
                );
            } else {
                $missing = collect($security['headers'] ?? [])->filter(fn ($v) => !$v)->count();
                if ($missing >= 3) {
                    $checks[] = self::check('security', 'warn', __('Güvenlik'), __(':count güvenlik başlığı eksik', ['count' => $missing]));
                    $issues[] = self::issue(
                        'security_headers',
                        self::SEVERITY_LOW,
                        __(':count güvenlik başlığı eksik', ['count' => $missing]),
                        __('HTTPS çalışıyor ama bazı güvenlik başlıkları (ör. HSTS, X-Content-Type-Options) tanımlı değil.'),
                        __('Sunucu yapılandırmanıza veya CDN ayarlarınıza eksik başlıkları ekleyin.'),
                    );
                } else {
                    $checks[] = self::check('security', 'ok', __('Güvenlik'), __('HTTPS zorunlu'));
                }
            }
        }

        // Gercek kullanici hizi (CrUX)
        $crux = $fact?->crux ?? [];
        if (($crux['configured'] ?? false) && ($crux['found'] ?? false)) {
            $ratings = collect($crux['metrics'] ?? [])->pluck('rating');
            if ($ratings->contains('poor')) {
                $checks[] = self::check('crux', 'fail', __('Sayfa hızı'), __('Gerçek kullanıcılar için yavaş'));
                $issues[] = self::issue(
                    'crux',
                    self::SEVERITY_MEDIUM,
                    __('Sayfalar gerçek kullanıcılar için yavaş'),
                    __('Chrome kullanıcılarından gelen son 28 günlük veride en az bir hız ölçümü "zayıf" seviyede.'),
                    __('Görselleri küçültün (WebP/AVIF), gereksiz eklenti ve betikleri kaldırın, önbelleklemeyi açın. Ayrıntı için "Site sağlığı" sekmesine bakın.'),
                );
            } elseif ($ratings->contains('needs_improvement')) {
                $checks[] = self::check('crux', 'warn', __('Sayfa hızı'), __('Geliştirilmeli'));
                $issues[] = self::issue(
                    'crux',
                    self::SEVERITY_LOW,
                    __('Sayfa hızı geliştirilebilir'),
                    __('Gerçek kullanıcı verisinde bazı hız ölçümleri "geliştirilmeli" seviyede.'),
                    __('Ayrıntılı ölçümler için "Site sağlığı" sekmesindeki CrUX bölümüne bakın.'),
                );
            } else {
                $checks[] = self::check('crux', 'ok', __('Sayfa hızı'), __('İyi'));
            }
        }

        // llms.txt
        $llms = $fact?->llms_txt ?? [];
        if ($llms !== []) {
            if ($llms['found'] ?? false) {
                $checks[] = self::check('llms_txt', 'ok', __('llms.txt'), __('Var'));
            } else {
                $checks[] = self::check('llms_txt', 'warn', __('llms.txt'), __('Yok'));
                $issues[] = self::issue(
                    'llms_txt',
                    self::SEVERITY_LOW,
                    __('llms.txt dosyası yok'),
                    __('Yapay zekâ asistanlarına sitenizi tanıtan kısa bir özet dosyası. Henüz yaygın bir standart değil ama eklemesi kolay.'),
                    __('Sitenizin köküne, ne yaptığınızı ve önemli sayfalarınızı listeleyen sade bir /llms.txt dosyası koyun.'),
                );
            }
        }

        usort($issues, fn ($a, $b) => self::SEVERITY_ORDER[$a['severity']] <=> self::SEVERITY_ORDER[$b['severity']]);

        $score = 100;
        foreach ($issues as $issue) {
            $score -= self::PENALTIES[$issue['severity']];
        }

        return new self(true, max(0, $score), $checks, $issues);
    }

    public function tone(): string
    {
        if ($this->score === null) {
            return 'secondary';
        }

        return match (true) {
            $this->score >= 80 => 'success',
            $this->score >= 50 => 'warning',
            default => 'danger',
        };
    }

    public function summary(): string
    {
        if (!$this->hasData) {
            return __('Henüz kontrol edilmedi');
        }

        return $this->issues === []
            ? __('İyi durumda')
            : trans_choice('{1} 1 konu dikkat istiyor|[2,*] :count konu dikkat istiyor', count($this->issues), ['count' => count($this->issues)]);
    }

    private static function check(string $key, string $status, string $label, string $detail): array
    {
        return compact('key', 'status', 'label', 'detail');
    }

    private static function issue(string $key, string $severity, string $title, string $description, ?string $fix): array
    {
        return compact('key', 'severity', 'title', 'description', 'fix');
    }
}
