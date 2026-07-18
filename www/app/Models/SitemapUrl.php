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
        'onpage_data',
        'onpage_error',
        'onpage_checked_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
        'lighthouse_raw' => 'array',
        'lighthouse_checked_at' => 'datetime',
        'onpage_data' => 'array',
        'onpage_checked_at' => 'datetime',
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
}
