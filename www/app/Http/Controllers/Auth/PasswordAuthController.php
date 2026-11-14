<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * E-posta + sifre ile giris ve kayit. Yeni hesaplar Google girisindeki
 * gibi admin onayi bekler; ayrica e-posta dogrulanana kadar panele
 * erisemez (bkz. EnsureUserApproved, EmailVerificationController).
 */
class PasswordAuthController extends Controller
{
    private const int MAX_LOGIN_ATTEMPTS = 5;

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($credentials['email']);
        $throttleKey = Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Çok fazla deneme yaptınız. :seconds saniye sonra tekrar deneyin.', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('E-posta veya şifre hatalı.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => __('Bu e-posta ile zaten bir hesap var. Giriş yapın ya da şifrenizi sıfırlayın.'),
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => app()->getLocale(),
        ]);

        // Dogrulama e-postasini Laravel'in Registered dinleyicisi gonderir.
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
