<?php

declare(strict_types=1);

namespace EzPhp\Audit;

use PDO;

/**
 * Class AuditLogger
 *
 * Writes audit records to the `audit_logs` database table via PDO.
 *
 * Production schema (MySQL):
 *
 *   CREATE TABLE audit_logs (
 *       id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *       entity_type VARCHAR(255) NOT NULL,
 *       entity_id   VARCHAR(255) NOT NULL,
 *       action      VARCHAR(10)  NOT NULL,
 *       old_values  JSON         DEFAULT NULL,
 *       new_values  JSON         DEFAULT NULL,
 *       user_id     VARCHAR(255) DEFAULT NULL,
 *       created_at  DATETIME     NOT NULL,
 *       INDEX       idx_audit_entity  (entity_type, entity_id),
 *       INDEX       idx_audit_created (created_at)
 *   );
 *
 * ensureTable() auto-creates the table when it does not yet exist. The DDL
 * adapts to the PDO driver (SQLite for tests, MySQL for production). In
 * production, prefer running a proper migration instead of relying on ensureTable().
 *
 * @package EzPhp\Audit
 */
final class AuditLogger implements AuditLoggerInterface
{
    private bool $tableChecked = false;

    /**
     * @param PDO $pdo Injected PDO connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function log(AuditRecord $record): void
    {
        $this->ensureTable();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_logs
                    (entity_type, entity_id, action, old_values, new_values, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $record->entityType,
                $record->entityId,
                $record->action->value,
                $record->oldValues !== [] ? json_encode($record->oldValues, JSON_THROW_ON_ERROR) : null,
                $record->newValues !== [] ? json_encode($record->newValues, JSON_THROW_ON_ERROR) : null,
                $record->userId,
                $record->createdAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            throw new AuditException("Failed to write audit log: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create the audit_logs table if it does not yet exist.
     * Runs at most once per AuditLogger instance. Adapts DDL to the PDO driver.
     */
    public function ensureTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        $this->tableChecked = true;

        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $this->pdo->exec(
                    'CREATE TABLE IF NOT EXISTS audit_logs (
                        id          INTEGER PRIMARY KEY AUTOINCREMENT,
                        entity_type TEXT    NOT NULL,
                        entity_id   TEXT    NOT NULL,
                        action      TEXT    NOT NULL,
                        old_values  TEXT    DEFAULT NULL,
                        new_values  TEXT    DEFAULT NULL,
                        user_id     TEXT    DEFAULT NULL,
                        created_at  TEXT    NOT NULL
                    )'
                );
            } else {
                $this->pdo->exec(
                    'CREATE TABLE IF NOT EXISTS audit_logs (
                        id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        entity_type VARCHAR(255) NOT NULL,
                        entity_id   VARCHAR(255) NOT NULL,
                        action      VARCHAR(10)  NOT NULL,
                        old_values  JSON         DEFAULT NULL,
                        new_values  JSON         DEFAULT NULL,
                        user_id     VARCHAR(255) DEFAULT NULL,
                        created_at  DATETIME     NOT NULL,
                        INDEX       idx_audit_entity  (entity_type, entity_id),
                        INDEX       idx_audit_created (created_at)
                    )'
                );
            }
        } catch (\Throwable) {
            // Table may already exist — ignore DDL error
        }
    }
}
