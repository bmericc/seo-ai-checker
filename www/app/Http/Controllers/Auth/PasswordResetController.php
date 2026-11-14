<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\VerifiedUserOnboarding;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Sifremi unuttum. Google ile kayit olmus (sifresi olmayan) kullanicilar
 * da bu akisla bir sifre belirleyebilir; GOOGLE_LOGIN_ENABLED kapatilirsa
 * hesaplarina boyle girerler.
 */
class PasswordResetController extends Controller
{
    public function showRequest(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink(['email' => strtolower((string) $request->input('email'))]);

        // Hesabin var olup olmadigini belli etmemek icin hep ayni yanit.
        return back()->with('flash', [
            'type' => 'success',
            'message' => __('Bu e-posta ile bir hesap varsa şifre sıfırlama bağlantısı gönderildi.'),
        ]);
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request, VerifiedUserOnboarding $onboarding): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => strtolower((string) $request->input('email')),
                'password' => (string) $request->input('password'),
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $request->input('token'),
            ],
            function (User $user, string $password) use ($onboarding): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Sifirlama linki e-postaya gittigi icin sahiplik kanitlandi.
                if (!$user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                $onboarding->handle($user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => match ($status) {
                    Password::RESET_THROTTLED => __('Lütfen tekrar denemeden önce biraz bekleyin.'),
                    default => __('Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş. Yeni bir bağlantı isteyin.'),
                }]);
        }

        Auth::logout();

        return redirect()->route('login')->with('flash', [
            'type' => 'success',
            'message' => __('Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.'),
        ]);
    }
}
