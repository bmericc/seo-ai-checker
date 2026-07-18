<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainCheck extends Model
{
    protected $fillable = [
        'domain_id',
        'ai_crawlers',
        'sitemap',
        'llms_txt',
        'security_headers',
        'canonical_host',
        'crux',
        'suggested_keywords',
    ];

    protected $casts = [
        'ai_crawlers' => 'array',
        'sitemap' => 'array',
        'llms_txt' => 'array',
        'security_headers' => 'array',
        'canonical_host' => 'array',
        'crux' => 'array',
        'suggested_keywords' => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
