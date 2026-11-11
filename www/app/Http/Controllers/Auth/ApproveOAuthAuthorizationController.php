<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Mcp\McpScopes;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport'un onay controller'i; tek farki, istemci "write" istemese de
 * kullanici onay ekraninda "yazma izni de ver" kutusunu isaretlerse
 * scope'lara "write" eklenmesi. Grok gibi istemciler hic scope (ya da
 * yalnizca "mcp:use") istedigi icin aksi halde yazma tool'lari hic
 * gorunmuyordu. Istemcinin kendi kisitli scope listesi varsa
 * finalizeScopes yine "write"i eler; kutu da o durumda gosterilmez.
 */
class ApproveOAuthAuthorizationController extends ApproveAuthorizationController
{
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $authRequest = $this->getAuthRequestFromSession($request);

        if ($request->boolean('grant_write')) {
            $scopes = $authRequest->getScopes();
            $ids = array_map(fn ($scope) => $scope->getIdentifier(), $scopes);

            if (!in_array(McpScopes::WRITE, $ids, true)) {
                $authRequest->setScopes([...$scopes, new Scope(McpScopes::WRITE)]);
            }
        }

        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }
}
