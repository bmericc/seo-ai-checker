<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Auth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        $body = (array) $request->getParsedBody();
        $token = $body['csrf_token'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';

        if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
            $response = $this->responseFactory->createResponse(400);
            $response->getBody()->write('Gecersiz CSRF token. Sayfayi yenileyip tekrar deneyin.');

            return $response;
        }

        return $handler->handle($request);
    }
}
