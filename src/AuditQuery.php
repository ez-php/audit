<?php

declare(strict_types=1);

namespace EzPhp\Audit;

use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Class AuditQuery
 *
 * Fluent, immutable query builder for the audit_logs table.
 * Filter methods return a clone — the original query is never modified.
 *
 * Usage:
 *
 *   // All audit records for entity ID 42:
 *   $records = AuditQuery::for(User::class, 42)->get();
 *
 *   // Only UPDATE records in the last 30 days:
 *   $records = AuditQuery::for(User::class, 42)
 *       ->action(AuditAction::UPDATE)
 *       ->since(new DateTimeImmutable('-30 days'))
 *       ->get();
 *
 * Must be initialised via AuditQuery::setPdo() before calling for().
 * AuditServiceProvider::boot() handles this automatically.
 * Throws RuntimeException when called before initialisation (fail-fast).
 *
 * @package EzPhp\Audit
 */
final class AuditQuery
{
    private static ?PDO $staticPdo = null;

    private ?AuditAction $filterAction = null;

    private ?DateTimeImmutable $filterSince = null;

    private ?DateTimeImmutable $filterUntil = null;

    private ?string $filterUserId = null;

    private ?int $limitCount = null;

    /**
     * @param PDO    $queryPdo   PDO instance for query execution.
     * @param string $entityType Entity class name or identifier.
     * @param string $entityId   String representation of the entity primary key.
     */
    private function __construct(
        private readonly PDO $queryPdo,
        private readonly string $entityType,
        private readonly string $entityId,
    ) {
    }

    /**
     * Initialise the static PDO used by the for() factory.
     * Called by AuditServiceProvider::boot().
     */
    public static function setPdo(PDO $pdo): void
    {
        self::$staticPdo = $pdo;
    }

    /**
     * Reset the static PDO — used in test tearDown to prevent state leaking.
     */
    public static function resetPdo(): void
    {
        self::$staticPdo = null;
    }

    /**
     * Create a query scoped to a specific entity.
     *
     * @param string     $entityType Entity class name.
     * @param int|string $entityId   Entity primary key.
     *
     * @throws RuntimeException When AuditQuery has not been initialised.
     */
    public static function for(string $entityType, int|string $entityId): self
    {
        if (self::$staticPdo === null) {
            throw new RuntimeException(
                'AuditQuery is not initialised. Add AuditServiceProvider to your application.'
            );
        }

        return new self(self::$staticPdo, $entityType, (string) $entityId);
    }

    /**
     * Filter results to a specific action type.
     */
    public function action(AuditAction $action): self
    {
        $clone = clone $this;
        $clone->filterAction = $action;

        return $clone;
    }

    /**
     * Filter results to records created on or after the given date.
     */
    public function since(DateTimeImmutable $date): self
    {
        $clone = clone $this;
        $clone->filterSince = $date;

        return $clone;
    }

    /**
     * Filter results to records created on or before the given date.
     */
    public function until(DateTimeImmutable $date): self
    {
        $clone = clone $this;
        $clone->filterUntil = $date;

        return $clone;
    }

    /**
     * Filter results to records created by a specific user.
     *
     * @param int|string $userId User identifier.
     */
    public function byUser(int|string $userId): self
    {
        $clone = clone $this;
        $clone->filterUserId = (string) $userId;

        return $clone;
    }

    /**
     * Limit the number of records returned.
     */
    public function limit(int $count): self
    {
        $clone = clone $this;
        $clone->limitCount = $count;

        return $clone;
    }

    /**
     * Execute the query and return all matching records, ordered by created_at DESC.
     *
     * @return list<AuditRecord>
     */
    public function get(): array
    {
        [$sql, $params] = $this->buildQuery(
            'SELECT entity_type, entity_id, action, old_values, new_values, user_id, created_at'
        );

        $stmt = $this->queryPdo->prepare($sql);
        $stmt->execute($params);

        /** @var mixed $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        $records = [];

        foreach ($rows as $row) {
            $record = $this->rowToRecord($row);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Execute the query and return the first matching record (latest by created_at), or null.
     */
    public function first(): ?AuditRecord
    {
        $results = $this->limit(1)->get();

        return $results[0] ?? null;
    }

    /**
     * Execute a COUNT query and return the total number of matching records.
     */
    public function count(): int
    {
        [$sql, $params] = $this->buildQuery('SELECT COUNT(*)');

        $stmt = $this->queryPdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetchColumn();

        return is_numeric($result) ? (int) $result : 0;
    }

    /**
     * Build the SQL query string and parameter list from the current filter state.
     *
     * @return array{string, list<mixed>}
     */
    private function buildQuery(string $select): array
    {
        $sql = $select . ' FROM audit_logs WHERE entity_type = ? AND entity_id = ?';

        /** @var list<mixed> $params */
        $params = [$this->entityType, $this->entityId];

        if ($this->filterAction !== null) {
            $sql .= ' AND action = ?';
            $params[] = $this->filterAction->value;
        }

        if ($this->filterSince !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $this->filterSince->format('Y-m-d H:i:s');
        }

        if ($this->filterUntil !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $this->filterUntil->format('Y-m-d H:i:s');
        }

        if ($this->filterUserId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $this->filterUserId;
        }

        $sql .= ' ORDER BY created_at DESC';

        if ($this->limitCount !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $this->limitCount;
        }

        return [$sql, $params];
    }

    /**
     * Convert a raw database row to an AuditRecord instance.
     * Returns null when the row contains invalid data.
     *
     * @param mixed $row
     */
    private function rowToRecord(mixed $row): ?AuditRecord
    {
        if (!is_array($row)) {
            return null;
        }

        $action = AuditAction::tryFrom($this->extractString($row['action'] ?? null));

        if ($action === null) {
            return null;
        }

        $oldValues = $this->decodeJson($row['old_values'] ?? null);
        $newValues = $this->decodeJson($row['new_values'] ?? null);

        $userId = isset($row['user_id']) ? $this->extractString($row['user_id']) : null;

        try {
            $createdAt = new DateTimeImmutable($this->extractString($row['created_at'] ?? null, 'now'));
        } catch (\Throwable) {
            $createdAt = new DateTimeImmutable();
        }

        return new AuditRecord(
            entityType: $this->extractString($row['entity_type'] ?? null),
            entityId: $this->extractString($row['entity_id'] ?? null),
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $userId,
            createdAt: $createdAt,
        );
    }

    /**
     * Safely extract a string from a mixed value.
     * Scalars are cast to string; non-scalars return the default.
     */
    private function extractString(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Decode a JSON string into an associative array.
     * Returns an empty array on null, empty string, or decode failure.
     *
     * @param mixed $json
     *
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
