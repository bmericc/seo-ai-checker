<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LogoutAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $_SESSION = [];
        session_regenerate_id(true);

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
