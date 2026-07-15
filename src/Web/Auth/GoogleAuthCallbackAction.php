<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Auth;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Google;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class GoogleAuthCallbackAction
{
    /**
     * @param string[] $allowedEmails
     */
    public function __construct(
        private readonly Google $provider,
        private readonly array $allowedEmails,
        private readonly Twig $twig,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $state = $params['state'] ?? null;
        $storedState = $_SESSION['oauth2state'] ?? null;
        unset($_SESSION['oauth2state']);

        if ($state === null || $storedState === null || !hash_equals((string) $storedState, (string) $state)) {
            return $this->twig->render(
                $response->withStatus(400),
                'auth/error.html.twig',
                ['message' => 'Giris dogrulamasi basarisiz oldu (state uyusmazligi). Lutfen tekrar deneyin.']
            );
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', ['code' => $params['code'] ?? '']);
            $googleUser = $this->provider->getResourceOwner($token)->toArray();
        } catch (IdentityProviderException $e) {
            return $this->twig->render(
                $response->withStatus(400),
                'auth/error.html.twig',
                ['message' => 'Google ile giris basarisiz oldu: ' . $e->getMessage()]
            );
        }

        $email = isset($googleUser['email']) ? strtolower((string) $googleUser['email']) : null;
        $emailVerified = $googleUser['email_verified'] ?? true;

        if ($email === null || !$emailVerified || !$this->isAllowed($email)) {
            return $this->twig->render(
                $response->withStatus(403),
                'auth/error.html.twig',
                ['message' => sprintf('%s adresine bu panele erisim izni verilmemis.', $email ?? '(bilinmiyor)')]
            );
        }

        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $googleUser['name'] ?? $email;

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    private function isAllowed(string $email): bool
    {
        if ($this->allowedEmails === []) {
            return false;
        }

        foreach ($this->allowedEmails as $allowed) {
            if (strtolower($allowed) === $email) {
                return true;
            }
        }

        return false;
    }
}
