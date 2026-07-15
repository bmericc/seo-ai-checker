<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Auth;

use League\OAuth2\Client\Provider\Google;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GoogleAuthLoginAction
{
    public function __construct(private readonly Google $provider)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $authUrl = $this->provider->getAuthorizationUrl(['scope' => ['email', 'profile']]);
        $_SESSION['oauth2state'] = $this->provider->getState();

        return $response->withHeader('Location', $authUrl)->withStatus(302);
    }
}
