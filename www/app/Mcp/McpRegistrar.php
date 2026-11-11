<?php

declare(strict_types=1);

namespace App\Mcp;

use Laravel\Mcp\Server\Registrar;

/**
 * laravel/mcp'nin OAuth metadata'si yalnizca "mcp:use" scope'unu ilan eder.
 * Istemciler (Claude, ChatGPT...) hangi scope'lari isteyecegini bu
 * metadata'dan okudugu icin ilan edilen listeyi McpScopes::supported() belirler;
 * geri kalan her sey paketin kendi davranisi.
 */
class McpRegistrar extends Registrar
{
    /**
     * token_endpoint_auth_methods_supported ilan edilmez: ChatGPT'de calisan
     * radyoc MCP'sinin belgesinde bu alan yok; bizde ["none",
     * "client_secret_basic", "client_secret_post"] varken ChatGPT kesiften
     * sonra istemci kaydina gecmiyordu. DCR zaten yalnizca public (none)
     * istemci acar.
     */
    protected static function authorizationServerMetadata(string $oauthPrefix): array
    {
        $metadata = [
            ...parent::authorizationServerMetadata($oauthPrefix),
            'scopes_supported' => McpScopes::supported(),
        ];
        unset($metadata['token_endpoint_auth_methods_supported']);

        return $metadata;
    }

    protected static function protectedResourceMetadata(string $path): array
    {
        return [
            ...parent::protectedResourceMetadata($path),
            'scopes_supported' => McpScopes::supported(),
        ];
    }
}
