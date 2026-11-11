<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Keyword;
use App\Services\DataForSeo\KeywordMetricsUpdater;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Verilen anahtar kelimelerin DataForSEO metriklerini (hacim, CPC, KD,
 * niyet) arka planda tazeler. Kelime eklendiginde ve haftalik otomatik
 * kontrolde kuyruga birakilir.
 */
final class FetchKeywordMetrics implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    /**
     * @param  list<int>  $keywordIds
     */
    public function __construct(
        public readonly array $keywordIds,
        public readonly bool $force = false,
    ) {
    }

    public function handle(KeywordMetricsUpdater $updater): void
    {
        $keywords = Keyword::query()->whereIn('id', $this->keywordIds)->get();

        $updater->refresh($keywords, $this->force);
    }
}
