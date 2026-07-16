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
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
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
