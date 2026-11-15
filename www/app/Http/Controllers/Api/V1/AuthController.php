<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mcp\McpScopes;
use App\Models\MobileLoginToken;
use App\Models\User;
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
