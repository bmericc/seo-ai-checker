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
 * 3. Uygulama kodu POST /api/v1/auth/token ile bir API token'ina cevirir
 *    (native ekranlar bu token'la calisir). Eski surumler kodu WebView'da
 *    /mobile/session?code=... ile harcayip dogrudan oturum acar.
 * 4. Native ekrandaki "Web'de aç" baglantilari /mobile/web?to=... adresini
 *    Authorization basligiyla WebView'da acar; token'in kullanicisi icin
 *    WebView'da web oturumu acilir ve istenen sayfaya gidilir.
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
            // 1: WebView kabugu, 2: /api/v1 token'i ve native ekranlar.
            'mobile_api' => 2,
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

    public function web(Request $request): RedirectResponse
    {
        $user = $request->user('api');
        $to = (string) $request->query('to', '/');

        // Yalnizca bu sitedeki bir yola gidilir (//baska-site.com degil).
        if (preg_match('#^/(?![/\\\\])#', $to) !== 1) {
            $to = '/';
        }

        $web = Auth::guard('web');
        if ($web->check() && $web->id() !== $user->id) {
            $web->logout();
        }

        if (!$web->check()) {
            $web->login($user, remember: true);
            $request->session()->regenerate();
        }

        return redirect($to);
    }
}
