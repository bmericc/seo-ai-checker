<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WeeklySeoReportMail;
use App\Models\User;
use App\Services\Report\WeeklySeoReportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

/**
 * Bir kullanicinin sahip oldugu TUM domain'ler icin haftalik ozeti tek bir
 * e-postada toplar (bkz. SendWeeklySeoReports komutu, kullanici basina bir
 * job kuyruga birakir - RunDomainSiteCheck'in "her birim ayri job" desenini
 * izler). Locale burada, e-posta gonderilmeden HEMEN once, senkron olarak
 * ayarlanir (App::setLocale) - WeeklySeoReportMail kendisi ShouldQueue
 * degil, bu yuzden view render'i da ayni PHP sureci/istegi icinde, dogru
 * locale hala aktifken gerceklesir (bkz. o class'in yorumu).
 */
final class SendWeeklySeoReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(WeeklySeoReportBuilder $builder): void
    {
        $user = User::find($this->userId);
        if ($user === null || $user->email === null) {
            return;
        }

        $domainReports = [];
        foreach ($user->domains as $domain) {
            $report = $builder->build($domain);
            if ($report !== null) {
                $domainReports[] = $report;
            }
        }

        if (empty($domainReports)) {
            return;
        }

        App::setLocale($user->locale ?? 'tr');

        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDays(6);

        Mail::to($user->email)->send(new WeeklySeoReportMail(
            user: $user,
            domainReports: $domainReports,
            periodStart: $periodStart->format('d.m.Y'),
            periodEnd: $periodEnd->format('d.m.Y'),
        ));
    }
}
