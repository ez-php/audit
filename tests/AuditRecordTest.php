<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use EzPhp\Audit\AuditAction;
use EzPhp\Audit\AuditRecord;
use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;

/**
 * @covers \EzPhp\Audit\AuditRecord
 * @uses   \EzPhp\Audit\AuditAction
 * @uses   \EzPhp\Audit\Event\EntityCreatedEvent
 * @uses   \EzPhp\Audit\Event\EntityUpdatedEvent
 * @uses   \EzPhp\Audit\Event\EntityDeletedEvent
 */
final class AuditRecordTest extends TestCase
{
    public function testFromCreatedBuildsRecord(): void
    {
        $event = new EntityCreatedEvent('App\\User', 1, ['name' => 'Alice'], 42);

        $record = AuditRecord::fromCreated($event);

        self::assertSame('App\\User', $record->entityType);
        self::assertSame('1', $record->entityId);
        self::assertSame(AuditAction::CREATE, $record->action);
        self::assertSame([], $record->oldValues);
        self::assertSame(['name' => 'Alice'], $record->newValues);
        self::assertSame('42', $record->userId);
    }

    public function testFromCreatedWithNullUserIdSetsNull(): void
    {
        $record = AuditRecord::fromCreated(new EntityCreatedEvent('App\\User', 5, []));

        self::assertNull($record->userId);
    }

    public function testFromUpdatedBuildsRecord(): void
    {
        $event = new EntityUpdatedEvent('App\\User', 2, ['name' => 'Bob'], ['name' => 'Bobby'], 'user-99');

        $record = AuditRecord::fromUpdated($event);

        self::assertSame('App\\User', $record->entityType);
        self::assertSame('2', $record->entityId);
        self::assertSame(AuditAction::UPDATE, $record->action);
        self::assertSame(['name' => 'Bob'], $record->oldValues);
        self::assertSame(['name' => 'Bobby'], $record->newValues);
        self::assertSame('user-99', $record->userId);
    }

    public function testFromDeletedBuildsRecord(): void
    {
        $event = new EntityDeletedEvent('App\\Post', 'abc-123', ['title' => 'Hello'], null);

        $record = AuditRecord::fromDeleted($event);

        self::assertSame('App\\Post', $record->entityType);
        self::assertSame('abc-123', $record->entityId);
        self::assertSame(AuditAction::DELETE, $record->action);
        self::assertSame(['title' => 'Hello'], $record->oldValues);
        self::assertSame([], $record->newValues);
        self::assertNull($record->userId);
    }

    public function testFromCreatedSetsCreatedAtToCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $record = AuditRecord::fromCreated(new EntityCreatedEvent('E', 1, []));
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $record->createdAt->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $record->createdAt->getTimestamp());
    }

    public function testEntityIdIsCastToString(): void
    {
        $record = AuditRecord::fromCreated(new EntityCreatedEvent('E', 99, []));

        self::assertSame('99', $record->entityId);
    }
}
