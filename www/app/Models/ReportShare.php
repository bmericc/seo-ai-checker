<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Giris gerektirmeyen, salt okunur bir domain raporu linki (/r/{token}).
 * Rapor canlidir: her acilista domain'in o anki verisini gosterir; hangi
 * bolumlerin gorunecegini "sections" belirler.
 */
class ReportShare extends Model
{
    public const SECTIONS = ['summary', 'keywords', 'traffic', 'performance', 'ai'];

    /** Linkin gecerlilik secenekleri (gun); null = suresiz. */
    public const EXPIRY_OPTIONS = [7, 30, 90, null];

    protected $fillable = [
        'domain_id',
        'user_id',
        'token',
        'title',
        'sections',
        'expires_at',
        'revoked_at',
        'view_count',
        'last_viewed_at',
    ];

    protected $casts = [
        'sections' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    public static function sectionLabels(): array
    {
        return [
            'summary' => __('Sağlık özeti ve yapılacaklar'),
            'keywords' => __('Anahtar kelime sıralamaları'),
            'traffic' => __('Search Console ve GA4 trafiği'),
            'performance' => __('Sayfa hızı ve Lighthouse'),
            'ai' => __('AI Overview görünürlüğü'),
        ];
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function shows(string $section): bool
    {
        return in_array($section, $this->sections ?? [], true);
    }

    public function url(): string
    {
        return route('reports.public', $this->token);
    }
}
