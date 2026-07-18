<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitemapUrl extends Model
{
    protected $fillable = [
        'domain_id',
        'url',
        'first_seen_at',
        'last_seen_at',
        'removed_at',
        'lighthouse_performance',
        'lighthouse_seo',
        'lighthouse_accessibility',
        'lighthouse_best_practices',
        'lighthouse_raw',
        'lighthouse_error',
        'lighthouse_checked_at',
        'lighthouse_queued_at',
        'onpage_data',
        'onpage_error',
        'onpage_checked_at',
        'onpage_queued_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
        'lighthouse_raw' => 'array',
        'lighthouse_checked_at' => 'datetime',
        'lighthouse_queued_at' => 'datetime',
        'onpage_data' => 'array',
        'onpage_checked_at' => 'datetime',
        'onpage_queued_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function isLighthouseChecked(): bool
    {
        return $this->lighthouse_checked_at !== null;
    }

    public function isOnPageChecked(): bool
    {
        return $this->onpage_checked_at !== null;
    }

    /**
     * Bir "Lighthouse Tara" tiklamasi bu URL icin kuyruga is birakti mi ve
     * o is henuz sonuclanmadi mi (sonuclanınca checked_at, queued_at'i
     * gecer ve bu false doner - is basarisiz da olsa checker her zaman
     * checked_at'i gunceller, bkz. RunSitemapUrlLighthouseCheck).
     */
    public function isLighthouseQueued(): bool
    {
        return $this->lighthouse_queued_at !== null
            && (
                $this->lighthouse_checked_at === null
                || $this->lighthouse_checked_at->lessThan($this->lighthouse_queued_at)
            );
    }

    public function isOnPageQueued(): bool
    {
        return $this->onpage_queued_at !== null
            && (
                $this->onpage_checked_at === null
                || $this->onpage_checked_at->lessThan($this->onpage_queued_at)
            );
    }
}
