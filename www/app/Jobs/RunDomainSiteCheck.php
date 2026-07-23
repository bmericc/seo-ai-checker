<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DomainCheckRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * "Site Kontrolü Yap" butonunun kuyruk uzerinden calisan karsiligi -
 * gunluk zamanlanmis kontrol (bkz. routes/console.php) domain sayisi
 * arttikca web isteginde senkron calistirilamayacagi icin her domain'i
 * ayri bir job olarak kuyruga birakir. DomainCheckRunner robots.txt,
 * sitemap, llms.txt, guvenlik header'lari, CrUX, GSC, GA4 ve Bing
 * backlink kontrollerini sirayla calistirdigindan tek bir domain 30-60
 * saniye surebilir; timeout buna gore genis tutuldu.
 */
final class RunDomainSiteCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $domainId)
    {
    }

    public function handle(DomainCheckRunner $checkRunner): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain === null) {
            return;
        }

        $checkRunner->run($domain);
    }
}
