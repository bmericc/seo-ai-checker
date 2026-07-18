<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Giris yapmis kullanici icin hesabina kayitli dili (Google hesabinin
 * dilinden tespit edilir, bkz. GoogleController) kullanir. Misafirler icin
 * (ya da henuz dili tespit edilememis kullanicilar icin) tarayicinin
 * Accept-Language header'ina bakilir, o da yoksa Turkce'ye dusulur.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $locale = $user?->locale;

        if (!in_array($locale, User::SUPPORTED_LOCALES, true)) {
            $locale = $request->getPreferredLanguage(User::SUPPORTED_LOCALES) ?? 'tr';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
