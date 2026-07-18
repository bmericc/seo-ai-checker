<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Bing Webmaster Tools'u kullaniciya ozel OAuth 2.0 ile baglar (Google'daki
 * gibi Socialite degil - Bing'in kendi Socialite driver'i olmadigi icin
 * standart authorization_code akisi elle uygulanir, bkz.
 * https://learn.microsoft.com/en-us/bingwebmaster/oauth2). Yalnizca zaten
 * giris yapmis kullanicilar icin (auth middleware) "hesap bagla" adimidir -
 * uygulamaya girisin kendisi hala yalnizca Google ile yapilir.
 */
class BingController extends Controller
{
    private const AUTHORIZE_ENDPOINT = 'https://www.bing.com/webmasters/oauth/authorize';

    private const TOKEN_ENDPOINT = 'https://www.bing.com/webmasters/oauth/token';

    private const SCOPE = 'webmaster.read';

    private const STATE_SESSION_KEY = 'bing_oauth_state';

    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function connect(): RedirectResponse
    {
        $state = Str::random(40);
        session([self::STATE_SESSION_KEY => $state]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.bing.client_id'),
            'redirect_uri' => route('bing.callback'),
            'scope' => self::SCOPE,
            'state' => $state,
        ]);

        return redirect(self::AUTHORIZE_ENDPOINT . '?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = session()->pull(self::STATE_SESSION_KEY);

        if ($request->query('error') !== null) {
            return $this->fail(__('Bing yetkilendirmesi reddedildi.'));
        }

        if (!is_string($expectedState) || $request->query('state') !== $expectedState) {
            return $this->fail(__('Bing yetkilendirmesi doğrulanamadı (durum uyuşmazlığı). Lütfen tekrar deneyin.'));
        }

        $code = $request->query('code');
        if (!is_string($code) || $code === '') {
            return $this->fail(__('Bing yetkilendirme kodu alınamadı.'));
        }

        try {
            $response = $this->client->post(self::TOKEN_ENDPOINT, [
                'form_params' => [
                    'client_id' => config('services.bing.client_id'),
                    'client_secret' => config('services.bing.client_secret'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => route('bing.callback'),
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return $this->fail(__('Bing token isteği başarısız oldu: :error', ['error' => $e->getMessage()]));
        }

        if ($response->getStatusCode() !== 200) {
            return $this->fail(__('Bing token isteği başarısız oldu (HTTP :status).', ['status' => $response->getStatusCode()]));
        }

        $data = json_decode((string) $response->getBody(), true);
        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            return $this->fail(__('Bing yanıtında access token bulunamadı.'));
        }

        $user = Auth::user();

        // Refresh token'i Bing yalnizca ilk yetkilendirmede doner - donmediyse
        // mevcut degeri koruyoruz.
        $user->forceFill([
            'bing_access_token' => $accessToken,
            'bing_refresh_token' => is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : $user->bing_refresh_token,
            'bing_token_expires_at' => is_int($expiresIn) ? now()->addSeconds($expiresIn) : $user->bing_token_expires_at,
        ])->save();

        return redirect()
            ->route('dashboard')
            ->with('flash', ['type' => 'success', 'message' => __('Bing hesabı bağlandı.')]);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()
            ->route('dashboard')
            ->with('flash', ['type' => 'error', 'message' => $message]);
    }
}
