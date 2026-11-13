<?php

declare(strict_types=1);

namespace App\Services\CrawlGraph;

/**
 * CrawlGraph aylik kotasi doldu (HTTP 429): ay basina kadar baska istek atma.
 */
final class CrawlGraphQuotaException extends CrawlGraphException
{
}
