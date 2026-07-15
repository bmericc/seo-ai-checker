<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\KeywordRepository;
use SeoAiChecker\Web\FlashBag;

final class KeywordDeleteAction
{
    public function __construct(private readonly KeywordRepository $keywords)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $keyword = $this->keywords->find((int) $args['id']);
        if ($keyword === null) {
            FlashBag::set('error', 'Anahtar kelime bulunamadi.');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $this->keywords->delete((int) $keyword['id']);
        FlashBag::set('success', 'Anahtar kelime silindi.');

        return $response->withHeader('Location', '/domains/' . $keyword['domain_id'])->withStatus(302);
    }
}
