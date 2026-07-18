<?php

declare(strict_types=1);

namespace App\Services\Ga4;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * GA4 Data API'sinden son 28 gunluk oturum/aktif kullanici verisini ceker,
 * "Organic Search" kanal grubuna dusen oturumlari ayrica raporlar. Property
 * ID otomatik tespit edilemedigi icin (bir Google hesabinda birden fazla
 * GA4 property olabilir, isim domain ile eslesmeyebilir) kullanici
 * tarafindan Domain formunda elle girilir - bkz. Domain::$ga4_property_id.
 */
final class Ga4Checker
{
    private const ENDPOINT = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';

    private const ORGANIC_CHANNEL = 'Organic Search';

    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function check(?string $propertyId, ?string $accessToken): Ga4Result
    {
        if ($propertyId === null || $propertyId === '' || $accessToken === null || $accessToken === '') {
            return new Ga4Result(configured: false, propertyId: $propertyId);
        }

        $startDate = '28daysAgo';
        $endDate = 'today';

        try {
            $response = $this->client->post(sprintf(self::ENDPOINT, $propertyId), [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'json' => [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                    'metrics' => [['name' => 'sessions'], ['name' => 'activeUsers']],
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            return new Ga4Result(configured: true, propertyId: $propertyId, error: $e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            return new Ga4Result(
                configured: true,
                propertyId: $propertyId,
                error: $this->describeError($response->getStatusCode(), (string) $response->getBody()),
            );
        }

        $data = json_decode((string) $response->getBody(), true);
        $rows = $data['rows'] ?? [];

        $totalSessions = 0.0;
        $totalActiveUsers = 0.0;
        $organicSessions = 0.0;

        foreach ($rows as $row) {
            $channel = $row['dimensionValues'][0]['value'] ?? null;
            $sessions = (float) ($row['metricValues'][0]['value'] ?? 0);
            $activeUsers = (float) ($row['metricValues'][1]['value'] ?? 0);

            $totalSessions += $sessions;
            $totalActiveUsers += $activeUsers;

            if ($channel === self::ORGANIC_CHANNEL) {
                $organicSessions += $sessions;
            }
        }

        return new Ga4Result(
            configured: true,
            propertyId: $propertyId,
            totalSessions: $totalSessions,
            organicSessions: $organicSessions,
            activeUsers: $totalActiveUsers,
            periodStart: $startDate,
            periodEnd: $endDate,
        );
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
