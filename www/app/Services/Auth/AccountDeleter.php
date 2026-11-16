<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Domain;
use App\Models\DomainMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Kullanicinin kendi hesabini silmesi (App Store / Google Play, uygulamada
 * hesap olusturulabiliyorsa uygulama icinden silmeyi zorunlu tutar).
 *
 * domains.user_id FK'si nullOnDelete oldugu icin kullaniciyi silmek tek
 * basina domain'leri sahipsiz birakir; burada sahip oldugu domain'ler (ve
 * cascade ile tum kontrol gecmisi) acikca silinir. Passport token'lari,
 * oturumlar ve bekleyen davetler de temizlenir.
 */
class AccountDeleter
{
    /**
     * Tek admin kendini silerse kimse yeni hesap onaylayamaz.
     */
    public function canDelete(User $user): bool
    {
        if (!$user->is_admin) {
            return true;
        }

        return User::query()
            ->where('is_admin', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->domains()->get()->each(fn (Domain $domain) => $domain->delete());

            DomainMember::query()
                ->whereNull('user_id')
                ->where('email', strtolower((string) $user->email))
                ->delete();

            $tokenIds = Token::query()->where('user_id', $user->getKey())->pluck('id');
            RefreshToken::query()->whereIn('access_token_id', $tokenIds)->delete();
            Token::query()->whereKey($tokenIds)->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_device_codes')->where('user_id', $user->getKey())->delete();

            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();
        });
    }
}
