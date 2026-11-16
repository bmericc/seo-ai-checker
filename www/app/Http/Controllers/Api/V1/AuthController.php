<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mcp\McpScopes;
use App\Models\MobileLoginToken;
use App\Models\User;
use App\Services\Auth\AccountDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mobil uygulamanin API token'i. Giris yine sitede (sistem tarayicisinda)
 * yapilir; /mobile/auth/start'in uygulamaya verdigi tek kullanimlik kod
 * burada salt okunur bir Passport kisisel erisim token'ina cevrilir.
 */
class AuthController extends Controller
{
    public const string TOKEN_NAME = 'Seo Checker mobile';

    public function store(Request $request): JsonResponse
    {
        $user = MobileLoginToken::consume((string) $request->input('code', ''));

        if ($user === null) {
            return response()->json([
                'error' => 'invalid_code',
                'message' => __('Uygulama giriş bağlantısının süresi dolmuş. Uygulamadan tekrar giriş yapın.'),
            ], 401);
        }

        $result = $user->createToken(self::TOKEN_NAME, [McpScopes::READ]);

        return response()->json([
            'token' => $result->accessToken,
            'expires_at' => $result->getToken()?->expires_at?->toIso8601String(),
            'user' => self::user($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => self::user($request->user())]);
    }

    /** Uygulamadan cikis: yalnizca bu cihazin token'i iptal edilir. */
    public function destroy(Request $request): Response
    {
        $request->user()->token()?->revoke();

        return response()->noContent();
    }

    /**
     * Hesabi kalici olarak siler (App Store / Google Play zorunlulugu).
     * Yalnizca mobil uygulamanin kendi token'i ile yapilabilir; MCP ile
     * yetkilendirilmis AI asistanlari hesap silemez. Yanlislikla cagriyi
     * onlemek icin hesabin e-posta adresi gonderilmelidir.
     */
    public function destroyAccount(Request $request, AccountDeleter $deleter): JsonResponse|Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->token()?->name !== self::TOKEN_NAME) {
            return response()->json([
                'error' => 'insufficient_scope',
                'message' => __('Bu işlem için yetkiniz yok.'),
            ], 403);
        }

        if (!$deleter->canDelete($user)) {
            return response()->json([
                'error' => 'last_admin',
                'message' => __('Tek admin olduğunuz için hesabınızı silemezsiniz. Önce başka bir kullanıcıyı admin yapın.'),
            ], 409);
        }

        if (strtolower(trim((string) $request->input('email'))) !== strtolower((string) $user->email)) {
            return response()->json([
                'error' => 'confirmation_required',
                'message' => __('Onaylamak için hesabınızın e-posta adresini yazın.'),
            ], 422);
        }

        $deleter->delete($user);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'locale' => $user->locale,
        ];
    }
}
