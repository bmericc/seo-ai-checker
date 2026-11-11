<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FetchKeywordMetrics;
use App\Jobs\RunKeywordCheck;
use App\Models\Keyword;
use App\Models\QueuedSerpTask;
use App\Services\DataForSeo\KeywordMetricsFetcher;
use App\Services\Serp\DataForSeoSerpScraper;
use App\Services\Serp\SerpScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Zamanlanmis haftalik anahtar kelime kontrolu (bkz. routes/console.php).
 *
 * DataForSEO tanimliysa her kelime icin desktop + mobil SERP gorevi
 * Standard kuyruga (task_post) birakilir; sonuclar hazir oldukca
 * CollectQueuedSerpResults onlari toplayip kelimenin tam kontrolunu
 * (on-page, Lighthouse) kuyruga birakir. DataForSEO yoksa her kelime icin
 * dogrudan RunKeywordCheck kuyruga birakilir.
 */
final class RunWeeklyKeywordChecks extends Command
{
    protected $signature = 'keywords:run-weekly-checks';

    protected $description = 'Tüm anahtar kelimeler için haftalık otomatik kontrolü başlatır';

    public function handle(SerpScraper $serpScraper, KeywordMetricsFetcher $metricsFetcher): int
    {
        if (!config('seo.scheduled_checks.enabled')) {
            $this->info('Haftalık otomatik kontrol kapalı (SCHEDULED_KEYWORD_CHECKS=false).');

            return self::SUCCESS;
        }

        $keywordIds = Keyword::query()->orderBy('id')->pluck('id')->all();

        if ($keywordIds === []) {
            $this->info('Kontrol edilecek anahtar kelime yok.');

            return self::SUCCESS;
        }

        if ($metricsFetcher->isConfigured()) {
            foreach (array_chunk($keywordIds, KeywordMetricsFetcher::BATCH_SIZE) as $chunk) {
                FetchKeywordMetrics::dispatch($chunk);
            }
        }

        if (!$serpScraper instanceof DataForSeoSerpScraper) {
            foreach ($keywordIds as $id) {
                RunKeywordCheck::dispatch($id, scheduled: true);
            }

            $this->info(count($keywordIds) . ' anahtar kelime için kontrol kuyruğa bırakıldı.');

            return self::SUCCESS;
        }

        $batch = (string) Str::uuid();
        $rows = [];
        foreach ($keywordIds as $id) {
            foreach ([SerpScraper::DEVICE_DESKTOP, SerpScraper::DEVICE_MOBILE] as $device) {
                $rows[] = QueuedSerpTask::query()->create(['keyword_id' => $id, 'batch' => $batch, 'device' => $device]);
            }
        }

        $keywords = Keyword::query()->whereIn('id', $keywordIds)->pluck('keyword', 'id');
        $posted = 0;

        foreach (array_chunk($rows, DataForSeoSerpScraper::MAX_TASKS_PER_POST) as $chunk) {
            try {
                $taskIds = $serpScraper->postTasks(array_map(
                    static fn (QueuedSerpTask $row) => [
                        'keyword' => (string) $keywords[$row->keyword_id],
                        'device' => $row->device,
                        'tag' => (string) $row->id,
                    ],
                    $chunk,
                ));
            } catch (Throwable $e) {
                // Ucretli istek tekrar denenmez; bu gorevler basarisiz sayilir
                // ve CollectQueuedSerpResults kelimeyi live SERP ile kontrol eder.
                $taskIds = [];
                $this->error($e->getMessage());
            }

            foreach ($chunk as $row) {
                $taskId = $taskIds[(string) $row->id] ?? null;
                $taskId !== null
                    ? $row->update(['task_id' => $taskId])
                    : $row->update(['error' => 'DataForSEO görevi oluşturulamadı.', 'completed_at' => now()]);
                $posted += $taskId !== null ? 1 : 0;
            }
        }

        $this->info(sprintf('%d anahtar kelime için %d SERP görevi Standard kuyruğa bırakıldı.', count($keywordIds), $posted));

        return self::SUCCESS;
    }
}
