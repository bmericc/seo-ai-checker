<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Kullanicinin MCP ile yetkilendirdigi uygulamalar (Passport istemcileri)
 * icin erisim iptali. Bir istemciye ait tum access ve refresh token'lar
 * iptal edilir; istemci yeniden baglanmak isterse onay ekrani tekrar acilir.
 */
class McpConnectionController extends Controller
{
    public function destroy(Request $request, string $client): RedirectResponse
    {
        $tokens = $request->user()->tokens()->where('client_id', $client)->where('revoked', false)->get();

        $tokens->each(function (Token $token): void {
            $token->revoke();
            RefreshToken::query()->where('access_token_id', $token->getKey())->update(['revoked' => true]);
        });

        return redirect()
            ->route('connections')
            ->with('flash', $tokens->isEmpty()
                ? ['type' => 'error', 'message' => __('Bu uygulama için etkin bir erişim bulunamadı.')]
                : ['type' => 'success', 'message' => __('Uygulamanın erişimi kaldırıldı.')]);
    }
}
