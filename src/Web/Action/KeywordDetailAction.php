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

final class KeywordDetailAction
{
    public function __construct(
        private readonly Twig $twig,
        private readonly KeywordRepository $keywords,
        private readonly DomainRepository $domains,
        private readonly CheckRepository $checks,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $keyword = $this->keywords->find((int) $args['id']);
        if ($keyword === null) {
            FlashBag::set('error', 'Anahtar kelime bulunamadi.');

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $domain = $this->domains->find((int) $keyword['domain_id']);
        $history = $this->checks->historyForKeyword((int) $keyword['id']);

        foreach ($history as &$row) {
            $row['organic_results'] = json_decode((string) ($row['organic_results_json'] ?? '[]'), true) ?: [];
            $row['ai_overview_cited_domains'] = json_decode((string) ($row['ai_overview_cited_domains_json'] ?? '[]'), true) ?: [];
            $row['onpage'] = $row['onpage_json'] !== null ? json_decode((string) $row['onpage_json'], true) : null;
        }
        unset($row);

        return $this->twig->render($response, 'keyword_detail.html.twig', [
            'domain' => $domain,
            'keyword' => $keyword,
            'history' => $history,
        ]);
    }
}
