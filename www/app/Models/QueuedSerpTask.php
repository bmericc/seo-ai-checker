<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Serp\SerpResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Haftalik otomatik kontrolde DataForSEO Standard kuyruguna birakilan tek
 * bir SERP gorevi (bir kelime + bir cihaz). Bkz. RunWeeklyKeywordChecks ve
 * CollectQueuedSerpResults.
 */
class QueuedSerpTask extends Model
{
    protected $fillable = [
        'keyword_id',
        'batch',
        'device',
        'task_id',
        'result',
        'error',
        'completed_at',
        'consumed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'completed_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function serpResult(): ?SerpResult
    {
        return is_array($this->result) ? SerpResult::fromArray($this->result) : null;
    }
}
