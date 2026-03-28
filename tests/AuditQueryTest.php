<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Audit\AuditAction;
use EzPhp\Audit\AuditLogger;
use EzPhp\Audit\AuditQuery;
use EzPhp\Audit\AuditRecord;
use PDO;
use RuntimeException;

/**
 * @covers \EzPhp\Audit\AuditQuery
 * @uses   \EzPhp\Audit\AuditAction
 * @uses   \EzPhp\Audit\AuditLogger
 * @uses   \EzPhp\Audit\AuditException
 * @uses   \EzPhp\Audit\AuditRecord
 */
final class AuditQueryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $logger = new AuditLogger($this->pdo);
        $logger->ensureTable();

        AuditQuery::setPdo($this->pdo);
    }

    protected function tearDown(): void
    {
        AuditQuery::resetPdo();
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function insert(
        string $entityType,
        string $entityId,
        AuditAction $action,
        string $createdAt = '2024-01-15 10:00:00',
        ?string $userId = null,
        array $oldValues = [],
        array $newValues = [],
    ): void {
        (new AuditLogger($this->pdo))->log(new AuditRecord(
            entityType: $entityType,
            entityId: $entityId,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $userId,
            createdAt: new DateTimeImmutable($createdAt),
        ));
    }

    public function testForReturnsAllRecordsForEntity(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE);
        $this->insert('App\\User', '1', AuditAction::UPDATE);
        $this->insert('App\\User', '2', AuditAction::CREATE);

        self::assertCount(2, AuditQuery::for('App\\User', 1)->get());
    }

    public function testForFiltersByEntityTypeAndId(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE);
        $this->insert('App\\Post', '1', AuditAction::CREATE);

        $records = AuditQuery::for('App\\User', 1)->get();

        self::assertCount(1, $records);
        self::assertSame('App\\User', $records[0]->entityType);
    }

    public function testFilterByAction(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE);
        $this->insert('App\\User', '1', AuditAction::UPDATE);
        $this->insert('App\\User', '1', AuditAction::DELETE);

        $records = AuditQuery::for('App\\User', 1)->action(AuditAction::UPDATE)->get();

        self::assertCount(1, $records);
        self::assertSame(AuditAction::UPDATE, $records[0]->action);
    }

    public function testFilterBySince(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE, '2024-01-10 00:00:00');
        $this->insert('App\\User', '1', AuditAction::UPDATE, '2024-01-20 00:00:00');

        $records = AuditQuery::for('App\\User', 1)
            ->since(new DateTimeImmutable('2024-01-15 00:00:00'))
            ->get();

        self::assertCount(1, $records);
        self::assertSame(AuditAction::UPDATE, $records[0]->action);
    }

    public function testFilterByUntil(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE, '2024-01-10 00:00:00');
        $this->insert('App\\User', '1', AuditAction::UPDATE, '2024-01-20 00:00:00');

        $records = AuditQuery::for('App\\User', 1)
            ->until(new DateTimeImmutable('2024-01-15 00:00:00'))
            ->get();

        self::assertCount(1, $records);
        self::assertSame(AuditAction::CREATE, $records[0]->action);
    }

    public function testFilterByUser(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE, userId: 'admin');
        $this->insert('App\\User', '1', AuditAction::UPDATE, userId: 'editor');

        $records = AuditQuery::for('App\\User', 1)->byUser('admin')->get();

        self::assertCount(1, $records);
        self::assertSame('admin', $records[0]->userId);
    }

    public function testLimitReducesResults(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE, '2024-01-10 00:00:00');
        $this->insert('App\\User', '1', AuditAction::UPDATE, '2024-01-20 00:00:00');
        $this->insert('App\\User', '1', AuditAction::DELETE, '2024-01-30 00:00:00');

        self::assertCount(2, AuditQuery::for('App\\User', 1)->limit(2)->get());
    }

    public function testResultsAreOrderedByCreatedAtDesc(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE, '2024-01-10 00:00:00');
        $this->insert('App\\User', '1', AuditAction::UPDATE, '2024-01-20 00:00:00');

        $records = AuditQuery::for('App\\User', 1)->get();

        self::assertSame(AuditAction::UPDATE, $records[0]->action);
        self::assertSame(AuditAction::CREATE, $records[1]->action);
    }

    public function testFirstReturnsLatestRecord(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE, '2024-01-10 00:00:00');
        $this->insert('App\\User', '1', AuditAction::UPDATE, '2024-01-20 00:00:00');

        $record = AuditQuery::for('App\\User', 1)->first();

        self::assertNotNull($record);
        self::assertSame(AuditAction::UPDATE, $record->action);
    }

    public function testFirstReturnsNullWhenNoRecords(): void
    {
        self::assertNull(AuditQuery::for('App\\User', 99)->first());
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE);
        $this->insert('App\\User', '1', AuditAction::UPDATE);

        self::assertSame(2, AuditQuery::for('App\\User', 1)->count());
    }

    public function testCountReturnsZeroWhenNoRecords(): void
    {
        self::assertSame(0, AuditQuery::for('App\\User', 99)->count());
    }

    public function testFiltersReturnClonesLeavingOriginalUnchanged(): void
    {
        $this->insert('App\\User', '1', AuditAction::CREATE);
        $this->insert('App\\User', '1', AuditAction::UPDATE);

        $base = AuditQuery::for('App\\User', 1);
        $filtered = $base->action(AuditAction::UPDATE);

        self::assertCount(2, $base->get());
        self::assertCount(1, $filtered->get());
    }

    public function testThrowsRuntimeExceptionWhenNotInitialised(): void
    {
        AuditQuery::resetPdo();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AuditQuery is not initialised');

        AuditQuery::for('App\\User', 1);
    }

    public function testRecordsDeserialiseJsonValues(): void
    {
        $this->insert(
            'App\\User',
            '1',
            AuditAction::UPDATE,
            oldValues: ['name' => 'Bob'],
            newValues: ['name' => 'Bobby'],
        );

        $record = AuditQuery::for('App\\User', 1)->first();

        self::assertNotNull($record);
        self::assertSame(['name' => 'Bob'], $record->oldValues);
        self::assertSame(['name' => 'Bobby'], $record->newValues);
    }

    public function testStringEntityIdIsAccepted(): void
    {
        $this->insert('App\\Post', 'slug-abc', AuditAction::CREATE);

        self::assertCount(1, AuditQuery::for('App\\Post', 'slug-abc')->get());
    }
}
