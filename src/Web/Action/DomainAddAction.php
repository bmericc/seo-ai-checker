<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Support\Domain;
use SeoAiChecker\Web\FlashBag;

final class DomainAddAction
{
    public function __construct(private readonly DomainRepository $domains)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $domain = Domain::fromFreeText((string) ($body['domain'] ?? ''));

        if ($domain === null) {
            FlashBag::set('error', 'Gecerli bir domain girin (ornek: example.com).');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $existing = $this->domains->findByName($domain);
        if ($existing !== null) {
            FlashBag::set('error', sprintf('%s zaten kayitli.', $domain));

            return $response->withHeader('Location', '/domains/' . $existing['id'])->withStatus(302);
        }

        $id = $this->domains->create($domain);
        FlashBag::set('success', sprintf('%s eklendi.', $domain));

        return $response->withHeader('Location', '/domains/' . $id)->withStatus(302);
    }
}
