<?php

declare(strict_types=1);

namespace App\Services\Bing;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Bing Webmaster API'sini kullanici adina cagirmak icin gecerli bir access
 * token dondurur - GoogleTokenService'in Bing karsiligi. Kullanicinin
 * refresh token'i yoksa (henuz "Bing hesabini bagla" akisini
 * tamamlamadiysa) null doner - BingBacklinksChecker bunu "bagli degil"
 * olarak yorumlar.
 */
final class BingTokenService
{
    private const TOKEN_ENDPOINT = 'https://www.bing.com/webmasters/token';

    public function __construct(
        private readonly Client $client,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly string $appUrl,
    ) {
    }

    public function getValidAccessToken(?User $user): ?string
    {
        if ($user === null || $user->bing_refresh_token === null) {
            return null;
        }

        $expiresAt = $user->bing_token_expires_at;
        if ($user->bing_access_token !== null && $expiresAt !== null && $expiresAt->subMinute()->isFuture()) {
            return $user->bing_access_token;
        }

        return $this->refresh($user);
    }

    private function refresh(User $user): ?string
    {
        if ($this->clientId === null || $this->clientSecret === null) {
            return null;
        }

        try {
            $response = $this->client->post(self::TOKEN_ENDPOINT, [
                // Bing'in token endpoint'i, tarayici disi (sunucu-sunucu)
                // isteklerde bile Origin/Referer header'i olmadan
                // "Origin and Referer request headers are both
                // absent/empty" hatasiyla HTTP 400 donuyor - bu yuzden
                // uygulamanin kendi URL'sini bu iki header olarak elle
                // ekliyoruz (2026-07-23'te prod'da tum refresh cagrilari
                // bu yuzden sessizce basarisiz oluyordu).
                'headers' => [
                    'Origin' => $this->appUrl,
                    'Referer' => $this->appUrl,
                ],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->bing_refresh_token,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode((string) $response->getBody(), true);
        $accessToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            return null;
        }

        $user->forceFill([
            'bing_access_token' => $accessToken,
            'bing_token_expires_at' => is_int($expiresIn) ? now()->addSeconds($expiresIn) : null,
        ])->save();

        return $accessToken;
    }
}
