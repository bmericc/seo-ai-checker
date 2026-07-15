<?php

declare(strict_types=1);

use League\OAuth2\Client\Provider\Google;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use SeoAiChecker\Http\HttpClientFactory;
use SeoAiChecker\Lighthouse\PageSpeedInsightsClient;
use SeoAiChecker\OnPage\OnPageSeoAnalyzer;
use SeoAiChecker\Repository\CheckRepository;
use SeoAiChecker\Repository\DomainRepository;
use SeoAiChecker\Repository\KeywordRepository;
use SeoAiChecker\Serp\GoogleSerpScraper;
use SeoAiChecker\Service\CheckRunner;
use SeoAiChecker\Web\Auth\GoogleAuthCallbackAction;
use SeoAiChecker\Web\FlashBag;
use Slim\Views\Twig;

return [
    'settings' => fn () => require __DIR__ . '/settings.php',

    ResponseFactoryInterface::class => fn () => new Slim\Psr7\Factory\ResponseFactory(),

    PDO::class => function (ContainerInterface $c) {
        $db = $c->get('settings')['db'];

        return buildPdo($db);
    },

    Twig::class => function (ContainerInterface $c) {
        $s = $c->get('settings')['twig'];
        $twig = Twig::create($s['template_path'], ['cache' => $s['cache_path']]);

        $twig->getEnvironment()->addGlobal('csrf_token', $_SESSION['csrf_token'] ?? '');
        $twig->getEnvironment()->addGlobal('current_user_email', $_SESSION['user_email'] ?? null);
        $twig->getEnvironment()->addGlobal('current_user_name', $_SESSION['user_name'] ?? null);
        $twig->getEnvironment()->addGlobal('flash', FlashBag::consume());

        return $twig;
    },

    DomainRepository::class => fn (ContainerInterface $c) => new DomainRepository($c->get(PDO::class)),
    KeywordRepository::class => fn (ContainerInterface $c) => new KeywordRepository($c->get(PDO::class)),
    CheckRepository::class => fn (ContainerInterface $c) => new CheckRepository($c->get(PDO::class)),

    GuzzleHttp\Client::class => function (ContainerInterface $c) {
        $serp = $c->get('settings')['serp'];
        $acceptLanguage = sprintf(
            '%s-%s,%s;q=0.9,en-US;q=0.8,en;q=0.7',
            $serp['hl'],
            strtoupper($serp['gl']),
            $serp['hl']
        );

        return HttpClientFactory::create($acceptLanguage, $serp['user_agent'], $serp['proxy']);
    },

    GoogleSerpScraper::class => function (ContainerInterface $c) {
        $serp = $c->get('settings')['serp'];

        return new GoogleSerpScraper($c->get(GuzzleHttp\Client::class), $serp['hl'], $serp['gl']);
    },

    OnPageSeoAnalyzer::class => fn (ContainerInterface $c) => new OnPageSeoAnalyzer($c->get(GuzzleHttp\Client::class)),

    PageSpeedInsightsClient::class => function (ContainerInterface $c) {
        $lh = $c->get('settings')['lighthouse'];

        return new PageSpeedInsightsClient($lh['psi_api_key'], $lh['psi_strategy']);
    },

    CheckRunner::class => fn (ContainerInterface $c) => new CheckRunner(
        $c->get(GoogleSerpScraper::class),
        $c->get(OnPageSeoAnalyzer::class),
        $c->get(PageSpeedInsightsClient::class),
        $c->get(CheckRepository::class),
    ),

    Google::class => function (ContainerInterface $c) {
        $o = $c->get('settings')['google_oauth'];

        return new Google([
            'clientId' => $o['client_id'],
            'clientSecret' => $o['client_secret'],
            'redirectUri' => $o['redirect_uri'],
        ]);
    },

    GoogleAuthCallbackAction::class => fn (ContainerInterface $c) => new GoogleAuthCallbackAction(
        $c->get(Google::class),
        $c->get('settings')['app']['allowed_google_emails'],
        $c->get(Twig::class),
    ),
];

function buildPdo(array $db): PDO
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if ($db['driver'] === 'sqlite') {
        $dir = dirname($db['path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $db['path'], null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS domains (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                domain TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS keywords (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                domain_id INTEGER NOT NULL REFERENCES domains(id) ON DELETE CASCADE,
                keyword TEXT NOT NULL,
                url TEXT,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                keyword_id INTEGER NOT NULL REFERENCES keywords(id) ON DELETE CASCADE,
                checked_at TEXT NOT NULL,
                blocked INTEGER NOT NULL DEFAULT 0,
                block_reason TEXT,
                target_position INTEGER,
                organic_results_json TEXT,
                ai_overview_present INTEGER NOT NULL DEFAULT 0,
                ai_overview_cited_domains_json TEXT,
                ai_overview_target_cited INTEGER,
                ai_overview_note TEXT,
                onpage_json TEXT,
                onpage_error TEXT,
                lighthouse_performance INTEGER,
                lighthouse_seo INTEGER,
                lighthouse_accessibility INTEGER,
                lighthouse_best_practices INTEGER,
                lighthouse_error TEXT
            );
        ');

        return $pdo;
    }

    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS domains (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain VARCHAR(255) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS keywords (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT NOT NULL,
            keyword VARCHAR(255) NOT NULL,
            url VARCHAR(2048),
            created_at DATETIME NOT NULL,
            FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS checks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            keyword_id INT NOT NULL,
            checked_at DATETIME NOT NULL,
            blocked TINYINT(1) NOT NULL DEFAULT 0,
            block_reason TEXT,
            target_position INT,
            organic_results_json MEDIUMTEXT,
            ai_overview_present TINYINT(1) NOT NULL DEFAULT 0,
            ai_overview_cited_domains_json TEXT,
            ai_overview_target_cited TINYINT(1),
            ai_overview_note TEXT,
            onpage_json MEDIUMTEXT,
            onpage_error TEXT,
            lighthouse_performance INT,
            lighthouse_seo INT,
            lighthouse_accessibility INT,
            lighthouse_best_practices INT,
            lighthouse_error TEXT,
            FOREIGN KEY (keyword_id) REFERENCES keywords(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    return $pdo;
}
