<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'approved_at', 'google_id', 'google_access_token', 'google_refresh_token', 'google_token_expires_at'])]
#[Hidden(['password', 'remember_token', 'google_access_token', 'google_refresh_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'approved_at' => 'datetime',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * GSC/GA4 gibi Google API'lerini cagirmak icin gereken offline erisim
     * (refresh token) var mi. Bu scope'lar sonradan eklendigi icin, daha
     * once giris yapmis kullanicilarin bunu almasi icin Google hesabini
     * tekrar baglamalari (yeniden consent vermesi) gerekir.
     */
    public function hasGoogleOfflineAccess(): bool
    {
        return $this->google_refresh_token !== null;
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
