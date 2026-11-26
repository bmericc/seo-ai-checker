<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Uygulamanin tek surum kaynagi: footer, MCP sunucusu ve config('app.version')
 * buradan okur. Yeni surumde sadece burayi ve CHANGELOG'u guncelleyin.
 */
final class AppVersion
{
    public const VERSION = '1.1.3';

    public const RELEASES_URL = 'https://github.com/bmericc/seo-ai-checker/releases/tag/v';

    public static function releaseUrl(): string
    {
        return self::RELEASES_URL.self::VERSION;
    }
}
