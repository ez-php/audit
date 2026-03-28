<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Audit\AuditAction;
use EzPhp\Audit\AuditLogger;
use EzPhp\Audit\AuditRecord;
use PDO;

/**
 * @covers \EzPhp\Audit\AuditLogger
 * @uses   \EzPhp\Audit\AuditAction
 * @uses   \EzPhp\Audit\AuditException
 * @uses   \EzPhp\Audit\AuditRecord
 */
final class AuditLoggerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function makeRecord(
        string $entityType = 'App\\User',
        string $entityId = '1',
        AuditAction $action = AuditAction::CREATE,
        array $oldValues = [],
        array $newValues = ['name' => 'Alice'],
        ?string $userId = '99',
        string $createdAt = '2024-01-15 10:00:00',
    ): AuditRecord {
        return new AuditRecord(
            entityType: $entityType,
            entityId: $entityId,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $userId,
            createdAt: new DateTimeImmutable($createdAt),
        );
    }

    public function testLogCreatesTableAndInsertsRecord(): void
    {
        $logger = new AuditLogger($this->pdo);

        $logger->log($this->makeRecord());

        $rows = $this->pdo->query('SELECT * FROM audit_logs')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        self::assertSame('App\\User', $rows[0]['entity_type']);
        self::assertSame('1', $rows[0]['entity_id']);
        self::assertSame('create', $rows[0]['action']);
        self::assertSame('99', $rows[0]['user_id']);
        self::assertSame('2024-01-15 10:00:00', $rows[0]['created_at']);
    }

    public function testLogStoresOldAndNewValuesAsJson(): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->log($this->makeRecord(
            action: AuditAction::UPDATE,
            oldValues: ['name' => 'Bob'],
            newValues: ['name' => 'Bobby'],
        ));

        $row = $this->pdo->query('SELECT old_values, new_values FROM audit_logs')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('{"name":"Bob"}', $row['old_values']);
        self::assertSame('{"name":"Bobby"}', $row['new_values']);
    }

    public function testLogStoresNullWhenValueArraysAreEmpty(): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->log($this->makeRecord(action: AuditAction::CREATE, oldValues: [], newValues: []));

        $row = $this->pdo->query('SELECT old_values, new_values FROM audit_logs')->fetch(PDO::FETCH_ASSOC);
        self::assertNull($row['old_values']);
        self::assertNull($row['new_values']);
    }

    public function testLogStoresNullUserId(): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->log($this->makeRecord(userId: null));

        $row = $this->pdo->query('SELECT user_id FROM audit_logs')->fetch(PDO::FETCH_ASSOC);
        self::assertNull($row['user_id']);
    }

    public function testMultipleRecordsCanBeLogged(): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->log($this->makeRecord(action: AuditAction::CREATE));
        $logger->log($this->makeRecord(action: AuditAction::UPDATE, oldValues: ['name' => 'A'], newValues: ['name' => 'B']));
        $logger->log($this->makeRecord(action: AuditAction::DELETE, oldValues: ['name' => 'B'], newValues: []));

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        self::assertSame(3, $count);
    }

    public function testEnsureTableIsIdempotent(): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->ensureTable();
        $logger->ensureTable();

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        self::assertSame(0, $count);
    }

    public function testAllThreeActionValuesAreStored(): void
    {
        $logger = new AuditLogger($this->pdo);
        $logger->log($this->makeRecord(action: AuditAction::CREATE));
        $logger->log($this->makeRecord(action: AuditAction::UPDATE, oldValues: ['x' => 1], newValues: ['x' => 2]));
        $logger->log($this->makeRecord(action: AuditAction::DELETE, oldValues: ['x' => 2], newValues: []));

        $rows = $this->pdo->query('SELECT action FROM audit_logs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['create', 'update', 'delete'], $rows);
    }
}
