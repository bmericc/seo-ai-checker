<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Services\Whois\WhoisChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Bir domain icin WHOIS/RDAP sorgusunu calistirip sonucu Domain kaydina
 * yazar. WHOIS sorgulari (TCP port 43 ya da RDAP HTTP istegi, registrar
 * sunucusuna dogrudan baglanti) yavas ve zaman zaman ulasilamaz
 * olabildigi icin bu her zaman kuyruk uzerinden calisir - "Domain Ekle"
 * ve "WHOIS Bilgisini Yenile" aksiyonlari sadece bu isi kuyruga birakir,
 * web istegi icinde WHOIS sorgusu asla dogrudan calistirilmaz.
 */
final class RunDomainWhoisLookup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public readonly int $domainId)
    {
    }

    public function handle(WhoisChecker $checker): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain === null) {
            return;
        }

        $result = $checker->check($domain->domain);

        $domain->update([
            'whois_registrar' => $result->registrar,
            'whois_registered_at' => $result->registeredAt,
            'whois_expires_at' => $result->expiresAt,
            'whois_raw' => $result->found ? $result->raw : null,
            'whois_error' => $result->error,
            'whois_checked_at' => now(),
        ]);
    }
}
