<?php

declare(strict_types=1);

use SeoAiChecker\Web\Action\DashboardAction;
use SeoAiChecker\Web\Action\DomainAddAction;
use SeoAiChecker\Web\Action\DomainDeleteAction;
use SeoAiChecker\Web\Action\DomainDetailAction;
use SeoAiChecker\Web\Action\KeywordAddAction;
use SeoAiChecker\Web\Action\KeywordCheckAction;
use SeoAiChecker\Web\Action\KeywordDeleteAction;
use SeoAiChecker\Web\Action\KeywordDetailAction;
use SeoAiChecker\Web\Auth\GoogleAuthCallbackAction;
use SeoAiChecker\Web\Auth\GoogleAuthLoginAction;
use SeoAiChecker\Web\Auth\LogoutAction;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app) {
    $app->add(TwigMiddleware::createFromContainer($app, Twig::class));

    $app->get('/login', GoogleAuthLoginAction::class);
    $app->get('/auth/google/callback', GoogleAuthCallbackAction::class);
    $app->get('/logout', LogoutAction::class);

    $app->get('/', DashboardAction::class);
    $app->post('/domains', DomainAddAction::class);
    $app->get('/domains/{id:[0-9]+}', DomainDetailAction::class);
    $app->post('/domains/{id:[0-9]+}/delete', DomainDeleteAction::class);
    $app->post('/domains/{id:[0-9]+}/keywords', KeywordAddAction::class);

    $app->get('/keywords/{id:[0-9]+}', KeywordDetailAction::class);
    $app->post('/keywords/{id:[0-9]+}/check', KeywordCheckAction::class);
    $app->post('/keywords/{id:[0-9]+}/delete', KeywordDeleteAction::class);
};
