<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ALLOWED_GOOGLE_EMAILS listesinden bir e-posta sonradan cikarilirsa, o
 * kullanicinin var olan oturumunun da hemen gecersiz kalmasini saglar.
 */
class EnsureGoogleEmailAllowed
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null) {
            $allowed = array_map('strtolower', config('seo.allowed_google_emails', []));

            if (!in_array(strtolower($user->email), $allowed, true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }
        }

        return $next($request);
    }
}
