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
 * Arayuz dilini secer. Oncelik sirasi:
 *  1. Giris yapmis kullanicinin Ayarlar'da sectigi (hesaba kayitli) dil.
 *  2. Misafirin sayfa altindan sectigi dil (locale cookie'si).
 *  3. Tarayicinin birincil dili: Turkce ise Turkce, degilse Ingilizce.
 */
class SetLocale
{
    public const string COOKIE = 'locale';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request, Auth::user()));

        return $next($request);
    }

    public static function resolve(Request $request, ?User $user = null): string
    {
        foreach ([$user?->locale, $request->cookie(self::COOKIE)] as $candidate) {
            if (is_string($candidate) && in_array($candidate, User::SUPPORTED_LOCALES, true)) {
                return $candidate;
            }
        }

        return self::fromBrowser($request);
    }

    public static function fromBrowser(Request $request): string
    {
        $primary = $request->getLanguages()[0] ?? '';

        return str_starts_with(strtolower($primary), 'tr') ? 'tr' : 'en';
    }
}
