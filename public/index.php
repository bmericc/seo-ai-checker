<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use SeoAiChecker\Web\Auth\AuthMiddleware;
use SeoAiChecker\Web\Auth\CsrfMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$builder = new ContainerBuilder();
$builder->addDefinitions(require __DIR__ . '/../config/container.php');
$container = $builder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(CsrfMiddleware::class);
$app->add(AuthMiddleware::class);
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) ($container->get('settings')['app']['debug']),
    logErrors: true,
    logErrorDetails: true,
);

(require __DIR__ . '/../config/routes.php')($app);

$app->run();
