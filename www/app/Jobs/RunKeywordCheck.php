<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Keyword;
use App\Services\CheckRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;

/**
 * Anahtar kelime kontrolunu (SERP + AI Overview + on-page + Lighthouse)
 * arka planda calistirir. Arayuz kontrolu senkron yapar; MCP istegi ise
 * dakikalar surebilecek bir islemi beklememeli.
 */
final class RunKeywordCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $keywordId)
    {
    }

    public function handle(CheckRunner $checkRunner): void
    {
        $keyword = Keyword::with('domain')->find($this->keywordId);

        if ($keyword === null) {
            return;
        }

        $checkRunner->run($keyword);
    }
}
