<?php

declare(strict_types=1);

namespace SeoAiChecker\Repository;

use PDO;

final class CheckRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO checks (
                keyword_id, checked_at, blocked, block_reason, target_position,
                organic_results_json, ai_overview_present, ai_overview_cited_domains_json,
                ai_overview_target_cited, ai_overview_note, onpage_json, onpage_error,
                lighthouse_performance, lighthouse_seo, lighthouse_accessibility,
                lighthouse_best_practices, lighthouse_error
            ) VALUES (
                :keyword_id, :checked_at, :blocked, :block_reason, :target_position,
                :organic_results_json, :ai_overview_present, :ai_overview_cited_domains_json,
                :ai_overview_target_cited, :ai_overview_note, :onpage_json, :onpage_error,
                :lighthouse_performance, :lighthouse_seo, :lighthouse_accessibility,
                :lighthouse_best_practices, :lighthouse_error
            )
        ');

        $stmt->execute([
            'keyword_id' => $data['keyword_id'],
            'checked_at' => $data['checked_at'] ?? gmdate('Y-m-d H:i:s'),
            'blocked' => (int) ($data['blocked'] ?? false),
            'block_reason' => $data['block_reason'] ?? null,
            'target_position' => $data['target_position'] ?? null,
            'organic_results_json' => $data['organic_results_json'] ?? null,
            'ai_overview_present' => (int) ($data['ai_overview_present'] ?? false),
            'ai_overview_cited_domains_json' => $data['ai_overview_cited_domains_json'] ?? null,
            'ai_overview_target_cited' => isset($data['ai_overview_target_cited'])
                ? (int) $data['ai_overview_target_cited']
                : null,
            'ai_overview_note' => $data['ai_overview_note'] ?? null,
            'onpage_json' => $data['onpage_json'] ?? null,
            'onpage_error' => $data['onpage_error'] ?? null,
            'lighthouse_performance' => $data['lighthouse_performance'] ?? null,
            'lighthouse_seo' => $data['lighthouse_seo'] ?? null,
            'lighthouse_accessibility' => $data['lighthouse_accessibility'] ?? null,
            'lighthouse_best_practices' => $data['lighthouse_best_practices'] ?? null,
            'lighthouse_error' => $data['lighthouse_error'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyForKeyword(int $keywordId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM checks WHERE keyword_id = ? ORDER BY checked_at DESC, id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $keywordId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestForKeyword(int $keywordId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM checks WHERE keyword_id = ? ORDER BY checked_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$keywordId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Verilen domaine ait tum keyword'ler icin en son check kaydini,
     * keyword_id => check satiri seklinde dondurur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestForDomain(int $domainId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.* FROM checks c
            INNER JOIN (
                SELECT keyword_id, MAX(checked_at) AS max_checked_at
                FROM checks
                INNER JOIN keywords ON keywords.id = checks.keyword_id
                WHERE keywords.domain_id = :domain_id
                GROUP BY keyword_id
            ) latest ON latest.keyword_id = c.keyword_id AND latest.max_checked_at = c.checked_at
        ');
        $stmt->execute(['domain_id' => $domainId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['keyword_id']] = $row;
        }

        return $result;
    }
}
