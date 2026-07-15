<?php

declare(strict_types=1);

namespace App\Support;

final class Domain
{
    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public static function equals(string $a, string $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }

    public static function fromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host !== false ? self::normalize($host) : null;
    }

    /**
     * Kullanicinin form alanina "example.com", "www.example.com" veya
     * "https://example.com/yol" gibi serbest metin olarak yazdigi bir
     * degerden gecerli bir host cikarir.
     */
    public static function fromFreeText(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (!str_contains($input, '://')) {
            $input = 'https://' . $input;
        }

        $host = parse_url($input, PHP_URL_HOST);
        if ($host === null || $host === false || $host === '') {
            return null;
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i', $host)) {
            return null;
        }

        return self::normalize($host);
    }
}
