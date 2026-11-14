<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\VerifiedUserOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    /**
     * Search Console/GA4 API'lerini kullanabilmek icin standart Socialite
     * girisine ek olarak "offline" erisim (refresh token) ve ilgili
     * scope'lar istenir. "prompt=consent" olmadan Google, kullanici daha
     * once onay verdiyse refresh token'i tekrar dondurmeyebilir.
     *
     * analytics.readonly (GA4) Google'da "sensitive scope" dogrulamasi
     * bekliyor (kullanim aciklamasi + demo video). Uygulama "Testing"
     * modundayken bu scope test kullanicilar icin zaten calisir - asil
     * kisitlama sadece dogrulanmamis uygulamayi test kullanicisi olmayan
     * herkese acmaktir.
     */
    private const GOOGLE_SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    public function redirect(): RedirectResponse
    {
        $this->ensureAllowed();

        return $this->driver()
            ->scopes(self::GOOGLE_SCOPES)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(VerifiedUserOnboarding $onboarding): RedirectResponse|Response
    {
        $this->ensureAllowed();

        try {
            $googleUser = $this->driver()->user();
        } catch (InvalidStateException $e) {
            return response()->view('auth.error', [
                'message' => __('Giriş doğrulaması başarısız oldu (state uyuşmazlığı). Lütfen tekrar deneyin.'),
            ], 400);
        }

        $email = strtolower((string) $googleUser->getEmail());

        if ($email === '') {
            return response()->view('auth.error', [
                'message' => __('Google hesabınızdan e-posta adresi alınamadı.'),
            ], 403);
        }

        // Giris yapmis kullanici Search Console/GA4 icin Google hesabini
        // bagliyor: oturumu degistirmeden token'lari ona yazariz. Google
        // e-postasi panel hesabinin e-postasindan farkli olabilir (orn.
        // e-posta + sifreyle kayit olmus biri).
        $current = Auth::user();

        if ($current !== null) {
            $taken = User::query()
                ->where('google_id', $googleUser->getId())
                ->whereKeyNot($current->getKey())
                ->exists();

            if ($taken) {
                return redirect()->route('connections')->with('flash', [
                    'type' => 'error',
                    'message' => __('Bu Google hesabı başka bir panel hesabına bağlı.'),
                ]);
            }

            $this->storeTokens($current, $googleUser);

            return redirect()->intended(route('connections'));
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $googleUser->getName() ?: $email],
        );

        // Google e-postayi dogrulamis olarak dondurur.
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $this->storeTokens($user, $googleUser);
        $onboarding->handle($user);

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Refresh token'i Google yalnizca ilk consent'te (ya da prompt=consent
     * ile tekrar consent verildiginde) doner - donmediyse mevcut degeri
     * koruyoruz, yoksa "Google hesabini bagla" akisi her seferinde
     * kullaniciyi tekrar tekrar consent ekranina gonderir ama yine de
     * eldeki refresh token'i sifirlamis oluruz.
     */
    private function storeTokens(User $user, \Laravel\Socialite\Contracts\User $googleUser): void
    {
        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'google_access_token' => $googleUser->token,
            'google_refresh_token' => $googleUser->refreshToken ?: $user->google_refresh_token,
            'google_token_expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : $user->google_token_expires_at,
            'locale' => $user->locale ?? app()->getLocale(),
        ])->save();
    }

    /**
     * GOOGLE_LOGIN_ENABLED=false iken misafirler Google ile giris yapamaz;
     * giris yapmis kullanicilar ise Search Console/GA4 icin Google
     * hesaplarini yine baglayabilir.
     */
    private function ensureAllowed(): void
    {
        abort_unless(Auth::check() || config('seo.google_login_enabled'), 404);
    }

    /**
     * Uygulama birden cok host'tan aciliyor (orn. seo-checker.tarti.com ve
     * seo-checker.tr). OAuth state'i baslatilan host'un session cookie'sinde
     * durdugu icin callback de ayni host'a donmeli; bu yuzden callback
     * istegin kendi host'undan uretilir. Her host'un
     * callback'i Google Console'da "Authorized redirect URIs" listesinde
     * olmali - Google kayitsiz redirect_uri'yi reddettigi icin sahte Host
     * basligi baska bir adrese yonlendirme yapamaz.
     *
     * @return \Laravel\Socialite\Two\GoogleProvider
     */
    private function driver(): mixed
    {
        return Socialite::driver('google')->redirectUrl(route('google.callback'));
    }
}
