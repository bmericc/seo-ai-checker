<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Mobil uygulamanin sistem tarayicisinda (Google girisi dahil) tamamlanan
 * girisi WebView'a tasiyan tek kullanimlik kod. Bkz. MobileAuthController.
 */
class MobileLoginToken extends Model
{
    public const int TTL_SECONDS = 120;

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Yeni bir kod uretir ve duz metin halini dondurur (yalnizca ozeti
     * saklanir). Suresi dolmus eski kodlar da bu sirada temizlenir.
     */
    public static function issueFor(User $user): string
    {
        static::query()->where('expires_at', '<', now()->subDay())->delete();

        $plain = Str::random(64);

        static::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return $plain;
    }

    /**
     * Gecerli, kullanilmamis bir kodun kullanicisini dondurur ve kodu
     * kullanildi olarak isaretler; aksi halde null.
     */
    public static function consume(string $plain): ?User
    {
        $token = static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($token === null) {
            return null;
        }

        // Ayni kod iki istekte ayni anda kullanilamasin.
        $claimed = static::query()
            ->whereKey($token->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return $claimed === 1 ? $token->user : null;
    }
}
