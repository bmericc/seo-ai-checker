<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kok adresteki MCP ucu (POST /) 401 dondugunde istemciyi kok adresin
 * OAuth metadata'sina yonlendirir. Paketin AddWwwAuthenticateHeader'i
 * yolu metadata adresine ekledigi icin kok adreste kullanilamiyor.
 */
class AddRootMcpWwwAuthenticateHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 401) {
            $response->headers->set(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="' . route('mcp.oauth.protected-resource') . '"'
            );
        }

        return $response;
    }
}
