<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Audit\AuditAction;
use EzPhp\Audit\AuditListener;
use EzPhp\Audit\AuditLoggerInterface;
use EzPhp\Audit\AuditRecord;
use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;
use EzPhp\Events\EventInterface;

/**
 * @covers \EzPhp\Audit\AuditListener
 * @uses   \EzPhp\Audit\AuditAction
 * @uses   \EzPhp\Audit\AuditRecord
 * @uses   \EzPhp\Audit\Event\EntityCreatedEvent
 * @uses   \EzPhp\Audit\Event\EntityUpdatedEvent
 * @uses   \EzPhp\Audit\Event\EntityDeletedEvent
 */
final class AuditListenerTest extends TestCase
{
    public function testHandlesEntityCreatedEvent(): void
    {
        $spy = new SpyAuditLogger();
        $listener = new AuditListener($spy);

        $listener->handle(new EntityCreatedEvent('App\\User', 1, ['name' => 'Alice']));

        self::assertCount(1, $spy->records);
        self::assertSame(AuditAction::CREATE, $spy->records[0]->action);
        self::assertSame('App\\User', $spy->records[0]->entityType);
        self::assertSame('1', $spy->records[0]->entityId);
        self::assertSame(['name' => 'Alice'], $spy->records[0]->newValues);
    }

    public function testHandlesEntityUpdatedEvent(): void
    {
        $spy = new SpyAuditLogger();
        $listener = new AuditListener($spy);

        $listener->handle(new EntityUpdatedEvent('App\\User', 2, ['name' => 'Bob'], ['name' => 'Bobby']));

        self::assertCount(1, $spy->records);
        self::assertSame(AuditAction::UPDATE, $spy->records[0]->action);
        self::assertSame(['name' => 'Bob'], $spy->records[0]->oldValues);
        self::assertSame(['name' => 'Bobby'], $spy->records[0]->newValues);
    }

    public function testHandlesEntityDeletedEvent(): void
    {
        $spy = new SpyAuditLogger();
        $listener = new AuditListener($spy);

        $listener->handle(new EntityDeletedEvent('App\\Post', 3, ['title' => 'Hello']));

        self::assertCount(1, $spy->records);
        self::assertSame(AuditAction::DELETE, $spy->records[0]->action);
        self::assertSame(['title' => 'Hello'], $spy->records[0]->oldValues);
        self::assertSame([], $spy->records[0]->newValues);
    }

    public function testIgnoresUnknownEventTypes(): void
    {
        $spy = new SpyAuditLogger();
        $listener = new AuditListener($spy);

        $listener->handle(new class implements EventInterface {
        });

        self::assertCount(0, $spy->records);
    }

    public function testUserIdIsForwardedToRecord(): void
    {
        $spy = new SpyAuditLogger();
        $listener = new AuditListener($spy);

        $listener->handle(new EntityCreatedEvent('App\\User', 5, [], 'admin'));

        self::assertSame('admin', $spy->records[0]->userId);
    }
}

/**
 * @internal
 */
final class SpyAuditLogger implements AuditLoggerInterface
{
    /** @var list<AuditRecord> */
    public array $records = [];

    public function log(AuditRecord $record): void
    {
        $this->records[] = $record;
    }
}
