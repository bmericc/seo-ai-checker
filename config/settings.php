<?php

declare(strict_types=1);

$dataDir = dirname(__DIR__) . '/database';

return [
    'db' => [
        'driver'  => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'host'    => $_ENV['DB_HOST']   ?? 'localhost',
        'name'    => $_ENV['DB_NAME']   ?? 'seo_ai_checker',
        'user'    => $_ENV['DB_USER']   ?? 'root',
        'pass'    => $_ENV['DB_PASS']   ?? '',
        'charset' => 'utf8mb4',
        'path'    => isset($_ENV['DB_PATH']) && $_ENV['DB_PATH'] !== ''
            ? $_ENV['DB_PATH']
            : $dataDir . DIRECTORY_SEPARATOR . 'seo_ai_checker.sqlite',
    ],
    'app' => [
        'debug'                 => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL),
        'base_url'              => rtrim($_ENV['APP_BASE_URL'] ?? '', '/'),
        'allowed_google_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', $_ENV['ALLOWED_GOOGLE_EMAILS'] ?? '')
        ))),
    ],
    'google_oauth' => [
        'client_id'     => $_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? '',
        'client_secret' => $_ENV['GOOGLE_OAUTH_CLIENT_SECRET'] ?? '',
        'redirect_uri'  => $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] ?? '',
    ],
    'serp' => [
        'hl'               => $_ENV['GOOGLE_HL'] ?? 'tr',
        'gl'                => $_ENV['GOOGLE_GL'] ?? 'tr',
        'request_delay_ms' => (int) ($_ENV['REQUEST_DELAY_MS'] ?? 4000),
        'proxy'            => $_ENV['HTTP_PROXY'] ?? null,
        'user_agent'       => $_ENV['USER_AGENT'] ?? null,
    ],
    'lighthouse' => [
        'psi_api_key'  => $_ENV['PSI_API_KEY'] ?? null,
        'psi_strategy' => $_ENV['PSI_STRATEGY'] ?? 'mobile',
    ],
    'twig' => [
        'template_path' => dirname(__DIR__) . '/templates',
        'cache_path'    => false,
    ],
];
