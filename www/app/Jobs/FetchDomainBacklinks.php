<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BacklinkSnapshot;
use App\Models\Domain;
use App\Services\DataForSeo\BacklinksFetcher;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Bir domain'in backlink profilini DataForSEO'dan ceker ve yeni bir
 * BacklinkSnapshot satiri yazar (hata olsa bile - sayfa hatayi gosterir).
 * Domain basina KEEP_PER_DOMAIN satirdan eskiler silinir.
 *
 * Ucretli istek oldugu icin otomatik tekrar yoktur ($tries = 1).
 */
final class FetchDomainBacklinks implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $domainId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->domainId;
    }

    public function handle(BacklinksFetcher $fetcher): void
    {
        $domain = Domain::find($this->domainId);
        if ($domain === null) {
            return;
        }

        try {
            if (!$fetcher->isConfigured()) {
                throw new DataForSeoException(__('DataForSEO yapılandırılmamış; backlink verisi çekilemiyor.'));
            }

            $data = $fetcher->fetch($domain->domain);
            $summary = $data['summary'];

            $domain->backlinkSnapshots()->create([
                'backlinks' => $summary['backlinks'] ?? null,
                'referring_domains' => $summary['referring_domains'] ?? null,
                'referring_domains_nofollow' => $summary['referring_domains_nofollow'] ?? null,
                'rank' => $summary['rank'] ?? null,
                'spam_score' => $summary['spam_score'] ?? null,
                // Liste isteklerinden biri patladiysa ozet yine de gecerlidir;
                // o listenin hatasi burada saklanir, liste null kalir.
                'summary' => $summary + ['list_errors' => $data['errors']],
                'top_referring_domains' => $data['top_referring_domains'],
                'anchors' => $data['anchors'],
                'new_referring_domains' => $data['new_referring_domains'],
                'lost_referring_domains' => $data['lost_referring_domains'],
                'cost' => $data['cost'],
                'error' => null,
                'fetched_at' => now(),
            ]);
        } catch (Throwable $e) {
            $domain->backlinkSnapshots()->create([
                'error' => $e->getMessage(),
                'fetched_at' => now(),
            ]);
        } finally {
            $domain->forceFill(['backlinks_requested_at' => null])->save();
        }

        $this->prune($domain);
    }

    private function prune(Domain $domain): void
    {
        $keepIds = $domain->backlinkSnapshots()
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->limit(BacklinkSnapshot::KEEP_PER_DOMAIN)
            ->pluck('id');

        $domain->backlinkSnapshots()->whereNotIn('id', $keepIds)->delete();
    }
}
