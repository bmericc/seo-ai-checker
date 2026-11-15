<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileLoginToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Android/iOS kabuk uygulamasinin girisi:
 *
 * 1. Uygulama sistem tarayicisinda (ASWebAuthenticationSession / Custom
 *    Tabs) /mobile/auth/start?state=... adresini acar. Giris yoksa 'auth'
 *    middleware'i /login'e gonderir; e-posta + sifre ya da Google girisi
 *    sitede tamamlanir ve intended() ile buraya donulur. Google, gomulu
 *    WebView'da girise izin vermedigi icin bu adim tarayicida olmali.
 * 2. Tek kullanimlik, 2 dakikalik bir kod uretilir ve uygulamaya
 *    seoaichecker://auth/callback?code=...&state=... ile donulur.
 * 3. Uygulama WebView'da /mobile/session?code=... acar; kod harcanir ve
 *    WebView'in kendi cerezlerinde oturum acilir.
 */
class MobileAuthController extends Controller
{
    /** Kod yalnizca uygulamanin kendi adresine gonderilir. */
    public const string REDIRECT_URI = 'seoaichecker://auth/callback';

    /**
     * Uygulamanin "sunucu adresi" ekraninda dogru bir sunucuya
     * baglandigini anlamasi icin.
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'app' => 'seo-ai-checker',
            'name' => config('app.name'),
            'mobile_api' => 1,
            'google_login' => (bool) config('seo.google_login_enabled'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');

        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) === 1, 400);

        $code = MobileLoginToken::issueFor($request->user());

        return redirect()->away(self::REDIRECT_URI.'?'.http_build_query([
            'code' => $code,
            'state' => $state,
        ]));
    }

    public function session(Request $request): RedirectResponse
    {
        $user = MobileLoginToken::consume((string) $request->query('code', ''));

        if ($user === null) {
            return redirect()->route('login')->with('flash', [
                'type' => 'error',
                'message' => __('Uygulama giriş bağlantısının süresi dolmuş. Uygulamadan tekrar giriş yapın.'),
            ]);
        }

        if (Auth::check() && Auth::id() !== $user->id) {
            Auth::logout();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/');
    }
}
