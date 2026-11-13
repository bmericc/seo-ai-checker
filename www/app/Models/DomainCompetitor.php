<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanicinin bir domain icin karar verdigi rakip: SERP kesfinden
 * "rakip olarak secilen" ya da elle eklenen domainler "tracked", "rakip
 * degil" denenler "ignored" olur. Sadece tracked rakiplerin one ciktigi
 * anahtar kelimeler cekilir (bkz. FetchCompetitorKeywords) ve
 * CompetitorKeywordGap ile takip edilen listeyle karsilastirilir.
 *
 * keywords: list<array{keyword: string, position: ?int, search_volume: ?int, url: ?string, score: ?float}>
 */
class DomainCompetitor extends Model
{
    public const TRACKED = 'tracked';

    public const IGNORED = 'ignored';

    public const STATUSES = [self::TRACKED, self::IGNORED];

    public const SOURCE_SERP = 'serp';

    public const SOURCE_MANUAL = 'manual';

    public const KEYWORDS_DATAFORSEO = 'dataforseo';

    public const KEYWORDS_CRAWL = 'crawl';

    protected $fillable = [
        'domain_id',
        'competitor_domain',
        'status',
        'source',
        'added_by_user_id',
        'keywords',
        'keywords_source',
        'keywords_fetched_at',
        'keywords_requested_at',
        'keywords_error',
    ];

    protected $casts = [
        'keywords' => 'array',
        'keywords_fetched_at' => 'datetime',
        'keywords_requested_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function isTracked(): bool
    {
        return $this->status === self::TRACKED;
    }

    /**
     * Istek atilmis ama henuz sonuc (ya da hata) yazilmamissa is kuyrukta/calisiyor demektir.
     */
    public function isFetchingKeywords(): bool
    {
        if ($this->keywords_requested_at === null) {
            return false;
        }

        // Takilmis bir is sayfayi sonsuza kadar "calisiyor" gostermesin.
        if ($this->keywords_requested_at->lt(now()->subMinutes(30))) {
            return false;
        }

        return $this->keywords_fetched_at === null || $this->keywords_fetched_at->lt($this->keywords_requested_at);
    }

    public function rootUrl(): string
    {
        return 'https://' . $this->competitor_domain . '/';
    }
}
