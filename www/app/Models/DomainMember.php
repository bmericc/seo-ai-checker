<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir domain'in sahibi disinda erisimi olan uye. Ayni domain'i her hesabin
 * ayri ayri eklemesi yerine sahip, domain'i baska kullanicilarla
 * paylasir:
 * - read: her seyi gorur, hicbir sey degistiremez.
 * - write: kontrol calistirir, anahtar kelime ekler/siler, rapor linki
 *   olusturur. Domain'i silemez, uyeleri yonetemez, GA4 property'sini
 *   degistiremez (o veri sahibin Google hesabindan gelir).
 */
class DomainMember extends Model
{
    public const READ = 'read';

    public const WRITE = 'write';

    public const PERMISSIONS = [self::READ, self::WRITE];

    protected $fillable = [
        'domain_id',
        'user_id',
        'email',
        'permission',
        'invited_by_user_id',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function canWrite(): bool
    {
        return $this->permission === self::WRITE;
    }

    public function isPending(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Henuz hesabi yokken davet edilmis e-postayi, o e-postayla giris yapan
     * kullaniciya baglar.
     */
    public static function claimPendingFor(User $user): void
    {
        static::query()
            ->whereNull('user_id')
            ->where('email', strtolower((string) $user->email))
            ->update(['user_id' => $user->id]);
    }
}
