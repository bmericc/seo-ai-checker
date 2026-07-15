<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Repository\KeywordRepository;
use SeoAiChecker\Web\FlashBag;

final class KeywordAddAction
{
    public function __construct(
        private readonly KeywordRepository $keywords,
        private readonly DomainRepository $domains,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $domainId = (int) $args['id'];
        if ($this->domains->find($domainId) === null) {
            FlashBag::set('error', 'Domain bulunamadi.');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $body = (array) $request->getParsedBody();
        $keyword = trim((string) ($body['keyword'] ?? ''));
        $url = trim((string) ($body['url'] ?? ''));

        if ($keyword === '') {
            FlashBag::set('error', 'Anahtar kelime bos olamaz.');

            return $response->withHeader('Location', '/domains/' . $domainId)->withStatus(302);
        }

        $this->keywords->create($domainId, $keyword, $url !== '' ? $url : null);
        FlashBag::set('success', sprintf('"%s" eklendi.', $keyword));

        return $response->withHeader('Location', '/domains/' . $domainId)->withStatus(302);
    }
}
