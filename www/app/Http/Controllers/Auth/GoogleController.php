<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /**
     * Search Console API'sini kullanabilmek icin standart Socialite girisine
     * ek olarak "offline" erisim (refresh token) ve ilgili scope istenir.
     * "prompt=consent" olmadan Google, kullanici daha once onay verdiyse
     * refresh token'i tekrar dondurmeyebilir.
     *
     * analytics.readonly (GA4) scope'u ILK ASAMADA KASITLI OLARAK
     * ISTENMIYOR: Google bu "sensitive scope" icin OAuth consent screen
     * dogrulamasinda kullanim aciklamasi + demo video istiyor. Bu
     * tamamlanana kadar Ga4Checker DomainCheckRunner'da devre disi
     * birakildi (bkz. DomainCheckRunner::GA4_ENABLED) - dogrulama
     * tamamlaninca buraya tekrar eklenmeli.
     */
    private const GOOGLE_SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
    ];

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(self::GOOGLE_SCOPES)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(): RedirectResponse|Response
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            return response()->view('auth.error', [
                'message' => 'Giris dogrulamasi basarisiz oldu (state uyusmazligi). Lutfen tekrar deneyin.',
            ], 400);
        }

        $email = strtolower((string) $googleUser->getEmail());

        if ($email === '') {
            return response()->view('auth.error', [
                'message' => 'Google hesabinizdan e-posta adresi alinamadi.',
            ], 403);
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $googleUser->getName() ?: $email],
        );

        if ($this->isBootstrapAdmin($email) && (!$user->is_admin || !$user->isApproved())) {
            $user->forceFill([
                'is_admin' => true,
                'approved_at' => $user->approved_at ?? now(),
            ])->save();
        }

        // Refresh token'i Google yalnizca ilk consent'te (ya da prompt=consent
        // ile tekrar consent verildiginde) doner - donmediyse mevcut degeri
        // koruyoruz, yoksa "Google hesabini bagla" akisi her seferinde
        // kullaniciyi tekrar tekrar consent ekranina gonderir ama yine de
        // eldeki refresh token'i sifirlamis oluruz.
        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'google_access_token' => $googleUser->token,
            'google_refresh_token' => $googleUser->refreshToken ?: $user->google_refresh_token,
            'google_token_expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : $user->google_token_expires_at,
        ])->save();

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended('/');
    }

    private function isBootstrapAdmin(string $email): bool
    {
        foreach (config('seo.bootstrap_admin_emails', []) as $candidate) {
            if (strtolower($candidate) === $email) {
                return true;
            }
        }

        return false;
    }
}
