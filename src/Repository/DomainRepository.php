<?php

declare(strict_types=1);

namespace SeoAiChecker\Repository;

use PDO;

final class DomainRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM domains ORDER BY domain ASC')->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $domain): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM domains WHERE domain = ?');
        $stmt->execute([$domain]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $domain): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO domains (domain, created_at) VALUES (?, ?)');
        $stmt->execute([$domain, gmdate('Y-m-d H:i:s')]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM domains WHERE id = ?');
        $stmt->execute([$id]);
    }
}
