<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Repository\KeywordRepository;
use SeoAiChecker\Service\CheckRunner;
use SeoAiChecker\Web\FlashBag;

final class KeywordCheckAction
{
    public function __construct(
        private readonly KeywordRepository $keywords,
        private readonly DomainRepository $domains,
        private readonly CheckRunner $checkRunner,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $keywordId = (int) $args['id'];
        $keyword = $this->keywords->find($keywordId);
        if ($keyword === null) {
            FlashBag::set('error', 'Anahtar kelime bulunamadi.');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $domain = $this->domains->find((int) $keyword['domain_id']);
        if ($domain === null) {
            FlashBag::set('error', 'Domain bulunamadi.');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $this->checkRunner->run($keyword, (string) $domain['domain']);
        FlashBag::set('success', 'Kontrol tamamlandi.');

        return $response->withHeader('Location', '/keywords/' . $keywordId)->withStatus(302);
    }
}
