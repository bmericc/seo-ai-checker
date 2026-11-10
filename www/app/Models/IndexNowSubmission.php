<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IndexNow'a yapilan tek bir URL bildirimi (bkz. IndexNowService::submit).
 * "source": hangi akistan geldigi - sitemap (tum aktif sitemap URL'leri),
 * manual (formdan elle girilen liste) ya da mcp (bir AI ajani).
 */
class IndexNowSubmission extends Model
{
    protected $table = 'indexnow_submissions';

    protected $fillable = [
        'domain_id',
        'source',
        'url_count',
        'urls',
        'status_code',
        'ok',
        'message',
    ];

    protected $casts = [
        'urls' => 'array',
        'ok' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
