<?php

declare(strict_types=1);

namespace SeoAiChecker\Web\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Repository\KeywordRepository;
use Slim\Views\Twig;

final class DashboardAction
{
    public function __construct(
        private readonly Twig $twig,
        private readonly DomainRepository $domains,
        private readonly KeywordRepository $keywords,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $domains = $this->domains->all();
        foreach ($domains as &$domain) {
            $domain['keyword_count'] = count($this->keywords->allForDomain((int) $domain['id']));
        }
        unset($domain);

        return $this->twig->render($response, 'dashboard.html.twig', ['domains' => $domains]);
    }
}
