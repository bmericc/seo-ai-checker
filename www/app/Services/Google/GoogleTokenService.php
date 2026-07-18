<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Google Search Console / GA4 gibi kullanici-adina calisan API'ler icin
 * gecerli bir access token dondurur. Kullanicinin refresh token'i yoksa
 * (henuz "Google hesabini bagla" akisini tamamlamadiysa) null doner -
 * checker'lar bunu "yapilandirilmamis/bagli degil" olarak yorumlar.
 */
final class GoogleTokenService
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly Client $client,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
    ) {
    }

    public function getValidAccessToken(?User $user): ?string
    {
        if ($user === null || $user->google_refresh_token === null) {
            return null;
        }

        $expiresAt = $user->google_token_expires_at;
        if ($user->google_access_token !== null && $expiresAt !== null && $expiresAt->subMinute()->isFuture()) {
            return $user->google_access_token;
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
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->google_refresh_token,
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
            'google_access_token' => $accessToken,
            'google_token_expires_at' => is_int($expiresIn) ? now()->addSeconds($expiresIn) : null,
        ])->save();

        return $accessToken;
    }
}
