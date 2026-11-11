<?php

declare(strict_types=1);

namespace App\Mcp;

use Laravel\Mcp\Server\Registrar;

/**
 * laravel/mcp'nin OAuth metadata'si yalnizca "mcp:use" scope'unu ilan eder.
 * Istemciler (Claude, ChatGPT...) hangi scope'lari isteyecegini bu
 * metadata'dan okudugu icin read/write scope'larimizi buraya ekliyoruz;
 * geri kalan her sey paketin kendi davranisi.
 */
class McpRegistrar extends Registrar
{
    protected static function authorizationServerMetadata(string $oauthPrefix): array
    {
        return [
            ...parent::authorizationServerMetadata($oauthPrefix),
            'scopes_supported' => McpScopes::supported(),
        ];
    }

    protected static function protectedResourceMetadata(string $path): array
    {
        return [
            ...parent::protectedResourceMetadata($path),
            'scopes_supported' => McpScopes::supported(),
        ];
    }
}
