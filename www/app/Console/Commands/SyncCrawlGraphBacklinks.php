<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CrawlGraphSnapshot;
use App\Models\Domain;
use App\Services\CrawlGraph\CrawlGraphClient;
use App\Services\CrawlGraph\CrawlGraphException;
use App\Services\CrawlGraph\CrawlGraphQuotaException;
use Illuminate\Console\Command;

/**
 * CrawlGraph (Common Crawl) link veren domain verisini senkronlar. Her gun
 * calisir ama yalnizca en yeni arsiv icin henuz basarili satiri olmayan
 * domain'leri sorgular - veri uc ayda bir yenilendigi icin her domain
 * arsiv basina tek bir istek harcar. Ucretsiz kota (ayda 15 istek)
 * dolarsa (HTTP 429) durur; kalan domain'ler kota ay basinda yenilenince
 * sonraki calismalarda sorgulanir.
 *
 * Eklenen / kaybolan domain'ler domain'in bir onceki arsivdeki basarili
 * satiriyla karsilastirilarak hesaplanir.
 */
final class SyncCrawlGraphBacklinks extends Command
{
    /** Eklenen / kaybolan listelerinde saklanan en fazla satir. */
    private const MAX_CHANGES = 200;

    protected $signature = 'backlinks:sync-crawlgraph';

    protected $description = 'Yeni Common Crawl arşivi için CrawlGraph link veren domain verisini çeker';

    public function handle(CrawlGraphClient $client): int
    {
        if (!config('seo.scheduled_checks.enabled')) {
            $this->info('Zamanlanmış kontroller kapalı (SCHEDULED_KEYWORD_CHECKS=false).');

            return self::SUCCESS;
        }

        if (!$client->isConfigured()) {
            $this->info('CRAWLGRAPH_API_KEY tanımlı değil; CrawlGraph senkronu atlandı.');

            return self::SUCCESS;
        }

        try {
            $release = $client->latestRelease();
        } catch (CrawlGraphException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($release === null) {
            $this->info('Sorgulanabilir CrawlGraph arşivi yok.');

            return self::SUCCESS;
        }

        $pending = Domain::query()
            ->whereDoesntHave('crawlGraphSnapshots', fn ($q) => $q->where('release_id', $release['id'])->whereNull('error'))
            ->orderBy('id')
            ->get();

        $done = 0;
        foreach ($pending as $domain) {
            try {
                $data = $client->backlinks($domain->domain, $release['id']);
            } catch (CrawlGraphQuotaException $e) {
                $this->warn($e->getMessage());

                break;
            } catch (CrawlGraphException $e) {
                $this->store($domain, $release, ['error' => $e->getMessage()]);

                continue;
            }

            $previous = $domain->crawlGraphSnapshots()
                ->where('release_id', '!=', $release['id'])
                ->whereNull('error')
                ->latest('fetched_at')
                ->first();

            $this->store($domain, $release, [
                'release_label' => $data['release_label'] ?? $release['label'],
                'total_linking_domains' => $data['total'],
                'authority' => $data['authority'],
                'rank' => $data['rank'],
                'linking_domains' => $data['domains'],
                'added_domains' => $previous === null ? null : $this->diff($data['domains'], $previous->linking_domains ?? []),
                'removed_domains' => $previous === null ? null : $this->diff($previous->linking_domains ?? [], $data['domains']),
                'error' => null,
            ]);
            $done++;
        }

        $this->info(sprintf('%s: %d domain senkronlandı, %d bekliyor.', $release['id'], $done, $pending->count() - $done));

        return self::SUCCESS;
    }

    /**
     * @param  array{id: string, label: ?string}  $release
     * @param  array<string, mixed>  $values
     */
    private function store(Domain $domain, array $release, array $values): void
    {
        CrawlGraphSnapshot::query()->updateOrCreate(
            ['domain_id' => $domain->id, 'release_id' => $release['id']],
            $values + ['release_label' => $release['label'], 'fetched_at' => now()],
        );
    }

    /**
     * $a'da olup $b'de olmayan domain'ler (authority'e gore sirali).
     *
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     * @return list<array<string, mixed>>
     */
    private function diff(array $a, array $b): array
    {
        $known = array_flip(array_column($b, 'domain'));

        return array_slice(array_values(array_filter($a, fn ($row) => !isset($known[$row['domain']]))), 0, self::MAX_CHANGES);
    }
}
