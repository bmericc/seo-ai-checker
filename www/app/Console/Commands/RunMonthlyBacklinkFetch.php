<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FetchDomainBacklinks;
use App\Models\Domain;
use App\Services\DataForSeo\BacklinksFetcher;
use Illuminate\Console\Command;

/**
 * Zamanlanmis aylik backlink cekimi (bkz. routes/console.php). Her
 * domain icin FetchDomainBacklinks kuyruga birakilir (~0,13$/domain).
 * Backlink verisi yavas degistigi icin ayda bir yeterli. Haftalik anahtar
 * kelime kontrolleriyle ayni anahtara
 * (SCHEDULED_KEYWORD_CHECKS) baglidir.
 */
final class RunMonthlyBacklinkFetch extends Command
{
    protected $signature = 'backlinks:run-monthly-fetch';

    protected $description = 'Tüm domainler için aylık backlink verisini çeker (DataForSEO)';

    public function handle(BacklinksFetcher $fetcher): int
    {
        if (!config('seo.scheduled_checks.enabled')) {
            $this->info('Zamanlanmış kontroller kapalı (SCHEDULED_KEYWORD_CHECKS=false).');

            return self::SUCCESS;
        }

        if (!$fetcher->isConfigured()) {
            $this->info('DataForSEO yapılandırılmamış; backlink çekimi atlandı.');

            return self::SUCCESS;
        }

        $count = 0;
        Domain::query()->orderBy('id')->each(function (Domain $domain) use (&$count) {
            $domain->forceFill(['backlinks_requested_at' => now()])->save();
            FetchDomainBacklinks::dispatch($domain->id);
            $count++;
        });

        $this->info($count . ' domain için backlink çekimi kuyruğa bırakıldı.');

        return self::SUCCESS;
    }
}
