<?php

declare(strict_types=1);

namespace App\Services\Robots;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Hedef domainin robots.txt dosyasini cekip bilinen AI crawler'larin
 * (GPTBot, ClaudeBot, PerplexityBot, Google-Extended vb.) analiz edilen
 * sayfaya erisip erisemedigini raporlar. Google'in klasik arama crawler'i
 * (Googlebot) bu listede DEGILDIR; AI Overview/arama gorunurlugu Googlebot
 * uzerinden calisir ve genelde ayri bir sekilde engellenmez, bu araci
 * yalnizca AI egitim/tarama crawler'larina odaklanir.
 */
final class RobotsTxtChecker
{
    private const AI_CRAWLERS = [
        'GPTBot' => 'OpenAI (egitim)',
        'ChatGPT-User' => 'OpenAI (ChatGPT tarama)',
        'OAI-SearchBot' => 'OpenAI (arama)',
        'ClaudeBot' => 'Anthropic (Claude)',
        'anthropic-ai' => 'Anthropic (egitim)',
        'PerplexityBot' => 'Perplexity',
        'Google-Extended' => 'Google (Gemini egitim)',
        'Bytespider' => 'ByteDance',
        'CCBot' => 'Common Crawl',
        'cohere-ai' => 'Cohere',
    ];

    public function __construct(private readonly Client $client)
    {
    }

    public function check(string $pageUrl): RobotsTxtResult
    {
        $robotsUrl = $this->robotsUrlFor($pageUrl);

        try {
            $response = $this->client->get($robotsUrl);
        } catch (GuzzleException) {
            return new RobotsTxtResult(url: $robotsUrl, found: false);
        }

        if ($response->getStatusCode() !== 200) {
            return new RobotsTxtResult(url: $robotsUrl, found: false);
        }

        $groups = $this->parseGroups((string) $response->getBody());
        $path = parse_url($pageUrl, PHP_URL_PATH) ?: '/';

        $crawlers = [];
        foreach (self::AI_CRAWLERS as $token => $label) {
            $crawlers[$token] = [
                'label' => $label,
                'allowed' => $this->isAllowed($groups, $token, $path),
            ];
        }

        return new RobotsTxtResult(url: $robotsUrl, found: true, crawlers: $crawlers);
    }

    private function robotsUrlFor(string $pageUrl): string
    {
        $parts = parse_url($pageUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        return sprintf('%s://%s/robots.txt', $scheme, $host);
    }

    /**
     * @return list<array{agents: list<string>, rules: list<array{type: string, path: string}>}>
     */
    private function parseGroups(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        $groups = [];
        $currentAgents = [];
        $currentRules = [];
        $collectingAgents = true;

        $flush = function () use (&$groups, &$currentAgents, &$currentRules): void {
            if ($currentAgents !== []) {
                $groups[] = ['agents' => $currentAgents, 'rules' => $currentRules];
            }
            $currentAgents = [];
            $currentRules = [];
        };

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (!$collectingAgents) {
                    $flush();
                }
                $currentAgents[] = strtolower($value);
                $collectingAgents = true;
            } elseif (in_array($field, ['allow', 'disallow'], true)) {
                $currentRules[] = ['type' => $field, 'path' => $value];
                $collectingAgents = false;
            }
        }
        $flush();

        return $groups;
    }

    /**
     * @param  list<array{agents: list<string>, rules: list<array{type: string, path: string}>}>  $groups
     */
    private function isAllowed(array $groups, string $token, string $path): bool
    {
        $rules = $this->rulesFor($groups, strtolower($token)) ?? $this->rulesFor($groups, '*');
        if ($rules === null) {
            return true;
        }

        $bestType = null;
        $bestLength = -1;

        foreach ($rules as $rule) {
            if ($rule['path'] === '') {
                continue;
            }

            if (!$this->pathMatches($path, $rule['path'])) {
                continue;
            }

            $length = strlen($rule['path']);
            if ($length > $bestLength || ($length === $bestLength && $rule['type'] === 'allow')) {
                $bestType = $rule['type'];
                $bestLength = $length;
            }
        }

        return $bestType !== 'disallow';
    }

    /**
     * @param  list<array{agents: list<string>, rules: list<array{type: string, path: string}>}>  $groups
     * @return list<array{type: string, path: string}>|null
     */
    private function rulesFor(array $groups, string $token): ?array
    {
        foreach ($groups as $group) {
            if (in_array($token, $group['agents'], true)) {
                return $group['rules'];
            }
        }

        return null;
    }

    private function pathMatches(string $path, string $rulePath): bool
    {
        $pattern = preg_quote($rulePath, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '#', $path);
    }
}
