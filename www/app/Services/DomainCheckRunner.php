<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\Robots\RobotsTxtChecker;

/**
 * Belirli bir anahtar kelime/URL'e degil, domain'in tamamina ait
 * kontrolleri (su an robots.txt AI crawler erisimi) calistirip sonucu
 * veritabanina yazan orkestrasyon servisi. CheckRunner'in domain seviyesindeki
 * karsiligi - ilerideki site-genel araclar (sitemap.xml, guvenlik header'lari
 * vb.) buraya eklenir.
 */
final class DomainCheckRunner
{
    public function __construct(
        private readonly RobotsTxtChecker $robotsTxtChecker,
    ) {
    }

    public function run(Domain $domain): DomainCheck
    {
        $robotsTxt = $this->robotsTxtChecker->check($domain->rootUrl());

        return $domain->domainChecks()->create([
            'ai_crawlers' => [
                'url' => $robotsTxt->url,
                'found' => $robotsTxt->found,
                'crawlers' => $robotsTxt->crawlers,
            ],
        ]);
    }
}
