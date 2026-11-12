<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Keyword;
use App\Models\QueuedSerpTask;
use App\Services\CheckRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;

/**
 * Anahtar kelime kontrolunu (SERP + AI Overview + on-page + Lighthouse)
 * arka planda calistirir. Arayuzdeki "Kontrol et" butonu ve MCP istegi
 * dakikalar surebilecek bu islemi beklememeli: senkron calisinca
 * nginx-proxy-manager/Cloudflare timeout'una (504) takiliyordu.
 *
 * Haftalik otomatik kontrolde $serpBatch verilir: SERP sonuclari zaten
 * DataForSEO Standard kuyrugundan toplanmistir (bkz.
 * CollectQueuedSerpResults), tekrar live sorgulanmaz.
 */
final class RunKeywordCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $keywordId,
        public readonly bool $scheduled = false,
        public readonly ?string $serpBatch = null,
    ) {
    }

    public function handle(CheckRunner $checkRunner): void
    {
        $keyword = Keyword::with('domain')->find($this->keywordId);

        if ($keyword === null) {
            return;
        }

        $prefetched = null;
        if ($this->serpBatch !== null) {
            $prefetched = QueuedSerpTask::query()
                ->where('keyword_id', $keyword->id)
                ->where('batch', $this->serpBatch)
                ->get()
                // Basarisiz/engellenmis kuyruk sonucu null'a cevrilir: masaustu
                // icin CheckRunner live SERP'e geri doner.
                ->mapWithKeys(function (QueuedSerpTask $task) {
                    $result = $task->serpResult();

                    return [$task->device => $result !== null && !$result->blocked ? $result : null];
                })
                ->all();
        }

        $checkRunner->run(
            $keyword,
            $prefetched,
            includeLlm: !$this->scheduled || (bool) config('seo.scheduled_checks.include_llm'),
            source: $this->scheduled ? 'scheduled' : 'manual',
        );
    }
}
