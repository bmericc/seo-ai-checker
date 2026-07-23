<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunDomainSiteCheck;
use App\Models\Domain;
use Illuminate\Console\Command;

/**
 * Zamanlanmis gunluk site kontrolu (bkz. routes/console.php) - kayitli
 * her domain icin RunDomainSiteCheck job'ini kuyruga birakir. Domain
 * basina tek tek job kullanilir ki bir domain'in yavas/basarisiz olmasi
 * digerlerini engellemesin ve worker mevcut queue:work isleyisiyle
 * (tries/backoff) ayni sekilde is sirasini yonetsin.
 */
final class RunDailySiteChecks extends Command
{
    protected $signature = 'domains:run-daily-checks';

    protected $description = 'Kayıtlı her domain için günlük site kontrolünü kuyruğa bırakır';

    public function handle(): int
    {
        $count = 0;

        Domain::query()->select('id')->orderBy('id')->chunkById(100, function ($domains) use (&$count) {
            foreach ($domains as $domain) {
                RunDomainSiteCheck::dispatch($domain->id);
                $count++;
            }
        });

        $this->info("{$count} domain için site kontrolü kuyruğa bırakıldı.");

        return self::SUCCESS;
    }
}
