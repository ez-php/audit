<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Contracts\DatabaseInterface;
use PDO;

/**
 * Minimal DatabaseInterface over an in-memory SQLite PDO, so provider tests can
 * bind a real connection without depending on the framework's Database.
 *
 * @package Tests\Support
 */
final class AuditSqliteDatabase implements DatabaseInterface
{
    private PDO $pdo;

    /**
     * AuditSqliteDatabase Constructor
     */
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * @param string            $sql
     * @param array<int|string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param string            $sql
     * @param array<int|string, mixed> $bindings
     *
     * @return int
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * @param callable $fn
     *
     * @return mixed
     */
    public function transaction(callable $fn): mixed
    {
        return $fn($this);
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
