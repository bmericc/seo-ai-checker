<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir domain icin tek bir DataForSEO Backlinks cekimi (bkz.
 * App\Services\DataForSeo\BacklinksFetcher, App\Jobs\FetchDomainBacklinks).
 */
class BacklinkSnapshot extends Model
{
    /** Domain basina tutulan en fazla satir; eskiler cekimden sonra silinir. */
    public const KEEP_PER_DOMAIN = 52;

    protected $fillable = [
        'domain_id',
        'backlinks',
        'referring_domains',
        'referring_domains_nofollow',
        'rank',
        'spam_score',
        'summary',
        'top_referring_domains',
        'anchors',
        'new_referring_domains',
        'lost_referring_domains',
        'cost',
        'error',
        'fetched_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'top_referring_domains' => 'array',
        'anchors' => 'array',
        'new_referring_domains' => 'array',
        'lost_referring_domains' => 'array',
        'cost' => 'float',
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
