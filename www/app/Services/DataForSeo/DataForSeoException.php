<?php

declare(strict_types=1);

namespace App\Services\DataForSeo;

use RuntimeException;

/**
 * DataForSEO istegi (ag, HTTP ya da zarf/gorev status_code) basarisiz
 * oldugunda firlatilir. Mesaj kullaniciya gosterilebilir duzeydedir.
 */
final class DataForSeoException extends RuntimeException
{
}
