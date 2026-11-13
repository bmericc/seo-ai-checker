<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Domain as DomainHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Tum domainler icin gecerli rakip kara listesi. Listede olan bir domain
 * (ve alt domainleri: facebook.com -> m.facebook.com) SERP rakip kesfine
 * girmez ve rakip olarak eklenemez. Admin, Ayarlar sayfasindan yonetir.
 */
class CompetitorBlacklist extends Model
{
    protected $table = 'competitor_blacklist';

    protected $fillable = ['domain', 'added_by_user_id'];

    private const CACHE_KEY = 'competitor-blacklist:domains';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return list<string>
     */
    public static function domains(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->orderBy('domain')->pluck('domain')->all());
    }

    /**
     * @param  ?list<string>  $blacklist  Onceden yuklenmis liste (dongulerde tekrar okumamak icin).
     */
    public static function matches(string $host, ?array $blacklist = null): bool
    {
        $host = DomainHelper::normalize($host);

        foreach ($blacklist ?? self::domains() as $entry) {
            if ($host === $entry || str_ends_with($host, '.' . $entry)) {
                return true;
            }
        }

        return false;
    }
}
