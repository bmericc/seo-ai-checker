<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Herkes Google ile giris yapip hesap olusturabilir, ancak bir admin
 * onaylayana kadar panele erisemez; bunun yerine "onay bekliyor" sayfasina
 * yonlendirilir. Onaylanmis bir kullanicinin onayi geri alinirsa (admin
 * panelden), bir sonraki istekte aninda bu sayfaya duser.
 */
class EnsureUserApproved
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && !$user->isApproved() && !$request->routeIs('pending-approval', 'logout')) {
            return redirect()->route('pending-approval');
        }

        return $next($request);
    }
}
