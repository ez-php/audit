<?php

declare(strict_types=1);

namespace Tests\Event;

use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Events\EventInterface;
use Tests\TestCase;

/**
 * @covers \EzPhp\Audit\Event\EntityDeletedEvent
 */
final class EntityDeletedEventTest extends TestCase
{
    public function testImplementsEventInterface(): void
    {
        self::assertInstanceOf(EventInterface::class, new EntityDeletedEvent('App\\Post', 1, []));
    }

    public function testPropertiesAreStoredCorrectly(): void
    {
        $event = new EntityDeletedEvent('App\\Post', 'abc', ['title' => 'Hello'], 'admin');

        self::assertSame('App\\Post', $event->entityType);
        self::assertSame('abc', $event->entityId);
        self::assertSame(['title' => 'Hello'], $event->oldValues);
        self::assertSame('admin', $event->userId);
    }

    public function testUserIdDefaultsToNull(): void
    {
        self::assertNull((new EntityDeletedEvent('App\\Post', 1, []))->userId);
    }
}
