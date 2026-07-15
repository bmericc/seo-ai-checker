<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\CheckRepository;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Repository\KeywordRepository;
use SeoAiChecker\Web\FlashBag;
use Slim\Views\Twig;

final class DomainDetailAction
{
    public function __construct(
        private readonly Twig $twig,
        private readonly DomainRepository $domains,
        private readonly KeywordRepository $keywords,
        private readonly CheckRepository $checks,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $domain = $this->domains->find((int) $args['id']);
        if ($domain === null) {
            FlashBag::set('error', 'Domain bulunamadi.');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $keywords = $this->keywords->allForDomain((int) $domain['id']);
        $latestChecks = $this->checks->latestForDomain((int) $domain['id']);

        return $this->twig->render($response, 'domain_detail.html.twig', [
            'domain' => $domain,
            'keywords' => $keywords,
            'latest_checks' => $latestChecks,
        ]);
    }
}
