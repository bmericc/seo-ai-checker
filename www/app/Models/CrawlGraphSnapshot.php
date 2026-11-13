<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir domain'in bir Common Crawl arsivindeki link veren domain'leri
 * (CrawlGraph). Bkz. App\Console\Commands\SyncCrawlGraphBacklinks.
 */
class CrawlGraphSnapshot extends Model
{
    protected $table = 'crawlgraph_snapshots';

    protected $fillable = [
        'domain_id',
        'release_id',
        'release_label',
        'total_linking_domains',
        'authority',
        'rank',
        'linking_domains',
        'added_domains',
        'removed_domains',
        'error',
        'fetched_at',
    ];

    protected $casts = [
        'linking_domains' => 'array',
        'added_domains' => 'array',
        'removed_domains' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isSuccessful(): bool
    {
        return $this->error === null;
    }
}
