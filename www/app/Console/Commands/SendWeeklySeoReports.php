<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendWeeklySeoReportJob;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Zamanlanmis haftalik e-posta raporu (bkz. routes/console.php, her
 * Pazartesi 08:00) - en az bir domain sahibi olan HER onayli kullanici
 * icin SendWeeklySeoReportJob'i kuyruga birakir (bkz. RunDailySiteChecks
 * ile ayni "her birim ayri job" deseni). Domain'i olmayan ya da
 * onaylanmamis kullanicilar atlanir; raporlanacak gercek veri olup
 * olmadigi (en az bir check calismis mi) job icinde, WeeklySeoReportBuilder
 * ile belirlenir - bos donerse o kullaniciya e-posta gonderilmez.
 */
final class SendWeeklySeoReports extends Command
{
    protected $signature = 'reports:send-weekly';

    protected $description = 'En az bir domaini olan her onaylı kullanıcı için haftalık SEO durum raporu e-postasını kuyruğa bırakır';

    public function handle(): int
    {
        $count = 0;

        User::query()
            ->whereNotNull('approved_at')
            ->whereHas('domains')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    SendWeeklySeoReportJob::dispatch($user->id);
                    $count++;
                }
            });

        $this->info("{$count} kullanıcı için haftalık SEO raporu kuyruğa bırakıldı.");

        return self::SUCCESS;
    }
}
