<?php

declare(strict_types=1);

namespace SeoAiChecker\Repository;

use PDO;

final class KeywordRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForDomain(int $domainId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM keywords WHERE domain_id = ? ORDER BY keyword ASC');
        $stmt->execute([$domainId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM keywords WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $domainId, string $keyword, ?string $url): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO keywords (domain_id, keyword, url, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$domainId, $keyword, $url, gmdate('Y-m-d H:i:s')]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM keywords WHERE id = ?');
        $stmt->execute([$id]);
    }
}
