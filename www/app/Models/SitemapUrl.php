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
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }
}
