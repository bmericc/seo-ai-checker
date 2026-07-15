<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Web\FlashBag;

final class DomainDeleteAction
{
    public function __construct(private readonly DomainRepository $domains)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->domains->delete((int) $args['id']);
        FlashBag::set('success', 'Domain ve tum kayitli anahtar kelimeleri silindi.');

        return $response->withHeader('Location', '/')->withStatus(302);
    }
}
