<?php

declare(strict_types=1);

namespace App\Services\Ga4;

/**
 * GA4'un "Default Channel Group" degerlerini (Direct, Referral, Organic
 * Social, Paid Search, Email, ...) grafikte gosterilen birkac kovaya
 * toplar. Ham kanal -> oturum haritasi DomainCheck.ga4.channel_sessions
 * altinda saklanir; kovalama gosterim zamaninda yapildigi icin eslemeyi
 * degistirmek eski kontrolleri de etkiler.
 *
 * "Organic Search" burada yok - zaten organic_sessions olarak ayrica
 * raporlaniyor. Paid Social "social" degil "paid" kovasina duser.
 *
 * Bkz. https://support.google.com/analytics/answer/9756891
 */
final class Ga4ChannelGroups
{
    public const DIRECT = 'direct';

    public const REFERRAL = 'referral';

    public const SOCIAL = 'social';

    public const PAID = 'paid';

    public const OTHER = 'other';

    public const BUCKETS = [self::DIRECT, self::REFERRAL, self::SOCIAL, self::PAID, self::OTHER];

    private const ORGANIC_SEARCH = 'Organic Search';

    private const EXACT = [
        'Direct' => self::DIRECT,
        'Referral' => self::REFERRAL,
        'Organic Social' => self::SOCIAL,
        'Display' => self::PAID,
        'Cross-network' => self::PAID,
        'Audio' => self::PAID,
    ];

    /**
     * @param  array<string, int|float>|null  $channelSessions
     * @return array<string, float>|null  Kova -> oturum; kanal verisi hic yoksa (eski kontrol, hata) null.
     */
    public static function group(?array $channelSessions): ?array
    {
        if ($channelSessions === null) {
            return null;
        }

        $buckets = array_fill_keys(self::BUCKETS, 0.0);

        foreach ($channelSessions as $channel => $sessions) {
            $channel = (string) $channel;

            if ($channel === self::ORGANIC_SEARCH) {
                continue;
            }

            $bucket = self::EXACT[$channel] ?? (str_starts_with($channel, 'Paid ') ? self::PAID : self::OTHER);
            $buckets[$bucket] += (float) $sessions;
        }

        return $buckets;
    }
}
