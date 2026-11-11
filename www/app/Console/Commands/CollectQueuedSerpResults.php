<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunKeywordCheck;
use App\Models\QueuedSerpTask;
use App\Services\Serp\DataForSeoSerpScraper;
use App\Services\Serp\SerpScraper;
use Illuminate\Console\Command;
use Throwable;

/**
 * DataForSEO Standard kuyrugundaki SERP gorevlerinin sonuclarini toplar
 * (task_get ucretsizdir) ve bir kelimenin tum cihaz sonuclari hazir
 * oldugunda o kelimenin tam kontrolunu (RunKeywordCheck) kuyruga birakir.
 * Birkac dakikada bir calisir (bkz. routes/console.php).
 */
final class CollectQueuedSerpResults extends Command
{
    protected $signature = 'keywords:collect-queued-serp';

    protected $description = 'Standard kuyruktaki SERP sonuçlarını toplar ve hazır kelimelerin kontrolünü başlatır';

    private const RETENTION_DAYS = 30;

    public function handle(SerpScraper $serpScraper): int
    {
        $timeout = now()->subHours((int) config('seo.scheduled_checks.queue_timeout_hours', 6));

        $pending = QueuedSerpTask::query()->with('keyword')->whereNull('completed_at')->get();

        foreach ($pending as $task) {
            if ($task->keyword === null) {
                continue;
            }

            if ($task->task_id === null || !$serpScraper instanceof DataForSeoSerpScraper) {
                $task->update(['error' => 'DataForSEO görevi bulunamadı.', 'completed_at' => now()]);

                continue;
            }

            try {
                $result = $serpScraper->fetchTask($task->task_id, $task->keyword->keyword);
            } catch (Throwable $e) {
                // Gecici bir hata olabilir; zaman asimina kadar tekrar denenir.
                $result = null;
                $this->warn($e->getMessage());
            }

            if ($result !== null) {
                $task->update(['result' => $result->toArray(), 'completed_at' => now()]);
            } elseif ($task->created_at->lt($timeout)) {
                $task->update(['error' => 'DataForSEO görevi zamanında tamamlanmadı.', 'completed_at' => now()]);
            }
        }

        $dispatched = $this->dispatchReadyKeywords();

        QueuedSerpTask::query()
            ->whereNotNull('consumed_at')
            ->where('consumed_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $this->info("{$dispatched} anahtar kelimenin kontrolü kuyruğa bırakıldı.");

        return self::SUCCESS;
    }

    /**
     * Tum gorevleri tamamlanmis (ama henuz kullanilmamis) kelime+batch
     * gruplari icin kontrol job'ini kuyruga birakir.
     */
    private function dispatchReadyKeywords(): int
    {
        $groups = QueuedSerpTask::query()
            ->whereNull('consumed_at')
            ->get()
            ->groupBy(fn (QueuedSerpTask $task) => $task->batch . ':' . $task->keyword_id);

        $count = 0;

        foreach ($groups as $tasks) {
            if ($tasks->contains(fn (QueuedSerpTask $task) => $task->completed_at === null)) {
                continue;
            }

            $first = $tasks->first();
            QueuedSerpTask::query()->whereIn('id', $tasks->pluck('id'))->update(['consumed_at' => now()]);
            RunKeywordCheck::dispatch($first->keyword_id, scheduled: true, serpBatch: $first->batch);
            $count++;
        }

        return $count;
    }
}
