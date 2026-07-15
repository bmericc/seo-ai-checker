<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Auth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    private const PUBLIC_PATHS = ['/login', '/auth/google/callback'];

    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (in_array($path, self::PUBLIC_PATHS, true)) {
            return $handler->handle($request);
        }

        if (!isset($_SESSION['user_email'])) {
            return $this->responseFactory->createResponse(302)->withHeader('Location', '/login');
        }

        return $handler->handle($request);
    }
}
