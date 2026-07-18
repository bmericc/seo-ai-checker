<?php

declare(strict_types=1);

namespace App\Services\Ga4;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * GA4 Admin API'sinden (accountSummaries.list) kullanicinin erisebildigi
 * hesap+property listesini ceker - kullanicinin GA4 Property ID'yi ezbere
 * kopyalamasi yerine bir listeden secmesini saglamak icin. Ga4Checker'in
 * aksine "hangi property" sorusuna cevap arar, veri cekmez.
 */
final class Ga4PropertyLister
{
    private const ENDPOINT = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries';

    public function __construct(
        private readonly Client $client,
    ) {
    }

    private const MAX_PAGES = 20;

    public function list(?string $accessToken): Ga4PropertyListResult
    {
        if ($accessToken === null || $accessToken === '') {
            return new Ga4PropertyListResult(configured: false);
        }

        $properties = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['pageSize' => 200];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            try {
                $response = $this->client->get(self::ENDPOINT, [
                    'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                    'query' => $query,
                    'http_errors' => false,
                ]);
            } catch (GuzzleException $e) {
                return new Ga4PropertyListResult(configured: true, error: $e->getMessage());
            }

            if ($response->getStatusCode() !== 200) {
                return new Ga4PropertyListResult(
                    configured: true,
                    error: $this->describeError($response->getStatusCode(), (string) $response->getBody()),
                );
            }

            $data = json_decode((string) $response->getBody(), true);

            foreach ($data['accountSummaries'] ?? [] as $account) {
                $accountName = $account['displayName'] ?? '';
                $accountName = $accountName !== '' ? $accountName : 'Diğer';

                foreach ($account['propertySummaries'] ?? [] as $property) {
                    $propertyId = $this->extractPropertyId($property['property'] ?? '');
                    if ($propertyId === null) {
                        continue;
                    }

                    $propertyName = $property['displayName'] ?? $propertyId;

                    $properties[] = new Ga4Property($propertyId, $propertyName, $accountName);
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;
            if ($pageToken === null || $pageToken === '') {
                break;
            }
        }

        return new Ga4PropertyListResult(configured: true, properties: $properties);
    }

    private function extractPropertyId(string $propertyResource): ?string
    {
        if (!str_starts_with($propertyResource, 'properties/')) {
            return $propertyResource !== '' ? $propertyResource : null;
        }

        $id = substr($propertyResource, strlen('properties/'));

        return $id !== '' ? $id : null;
    }

    private function describeError(int $status, string $body): string
    {
        $data = json_decode($body, true);
        $message = $data['error']['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return "HTTP {$status}: {$message}";
        }

        return "HTTP {$status}";
    }
}
