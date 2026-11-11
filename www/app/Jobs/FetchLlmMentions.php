<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Models\DomainFact;
use App\Services\DataForSeo\LlmMentionsChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Bir domain'in DataForSEO LLM Mentions verisini (AI cevaplarinda kac kez
 * gectigi, hangi sorularda gectigi) ceker ve domain STRING'ine ait
 * paylasilan DomainFact satirina yazar - kullanicidan bagimsiz bir veridir,
 * ayni domain'i takip eden herkes ayni sonucu gorur.
 */
final class FetchLlmMentions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $domainId)
    {
    }

    public function handle(LlmMentionsChecker $checker): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain === null || !$checker->isConfigured()) {
            return;
        }

        DomainFact::forDomain($domain->domain)->update([
            'llm_mentions' => $checker->check($domain->domain),
            'llm_mentions_checked_at' => now(),
        ]);
    }
}
