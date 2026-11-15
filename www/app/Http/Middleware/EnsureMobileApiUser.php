<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Mcp\McpScopes;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1 (mobil uygulama) istekleri icin, 'auth:api'den sonra calisir:
 * - Yanitlar kullanicinin Ayarlar'da sectigi dilde olur (web'deki
 *   SetLocale yalnizca web grubunda calisir).
 * - Token okuma iznine sahip olmali.
 * - E-postasi dogrulanmamis ya da henuz onaylanmamis kullanici veri
 *   goremez; uygulama bu durumda web'deki bekleme sayfasini acar.
 */
class EnsureMobileApiUser
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        App::setLocale(SetLocale::resolve($request, $user));

        if (!$user->tokenCan(McpScopes::READ) && !$user->tokenCan(McpScopes::WRITE)) {
            return response()->json([
                'error' => 'insufficient_scope',
                'message' => __('Bu işlem için yetkiniz yok.'),
            ], 403);
        }

        if (!$user->hasVerifiedEmail() || !$user->isApproved()) {
            return response()->json([
                'error' => 'pending',
                'message' => !$user->hasVerifiedEmail()
                    ? __('E-posta adresinizi doğrulamanız gerekiyor.')
                    : __('Hesabınız yönetici onayı bekliyor.'),
                'web_url' => url('/'),
            ], 403);
        }

        return $next($request);
    }
}
