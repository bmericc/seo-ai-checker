<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Http\Middleware\AddRootMcpWwwAuthenticateHeader;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Server\Middleware\ReorderJsonAccept;
use Laravel\Mcp\Server\Middleware\ValidateMcpHeaders;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Transport\HttpTransport;

/**
 * laravel/mcp'nin OAuth metadata'si yalnizca "mcp:use" scope'unu ilan eder.
 * Istemciler (Claude, ChatGPT...) hangi scope'lari isteyecegini bu
 * metadata'dan okudugu icin read/write scope'larimizi buraya ekliyoruz;
 * geri kalan her sey paketin kendi davranisi.
 */
class McpRegistrar extends Registrar
{
    /**
     * Ayni MCP sunucusunu kok adreste de (POST /) calistirir. Kullanicilar
     * connector adresine cogu zaman yalnizca site adresini yaziyor; Claude
     * o zaman kok adresin OAuth metadata'sini bulup token aliyor ama
     * istekleri POST / adresine gonderiyor. GET / tanitim sayfasi olarak
     * kalir - paketin web() metodu GET/DELETE icin 405 route'u da ekledigi
     * icin burada yalnizca POST kaydedilir.
     *
     * @param  class-string<\Laravel\Mcp\Server>  $serverClass
     */
    public function webAtRoot(string $serverClass): Route
    {
        $route = Router::post('/', static fn (): mixed => static::startServer(
            $serverClass,
            static fn (): HttpTransport => new HttpTransport(request()),
        ))->middleware([
            ReorderJsonAccept::class,
            ValidateMcpHeaders::class,
            AddRootMcpWwwAuthenticateHeader::class,
        ]);

        $this->httpServers[$route->uri()] = $route;

        return $route;
    }

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
