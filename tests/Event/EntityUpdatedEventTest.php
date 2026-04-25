<?php

declare(strict_types=1);

namespace Tests\Event;

use EzPhp\Audit\Event\EntityUpdatedEvent;
use EzPhp\Events\EventInterface;
use Tests\TestCase;

/**
 * @covers \EzPhp\Audit\Event\EntityUpdatedEvent
 */
final class EntityUpdatedEventTest extends TestCase
{
    public function testImplementsEventInterface(): void
    {
        self::assertInstanceOf(EventInterface::class, new EntityUpdatedEvent('App\\User', 1, [], []));
    }

    public function testPropertiesAreStoredCorrectly(): void
    {
        $event = new EntityUpdatedEvent(
            'App\\User',
            5,
            ['name' => 'Before'],
            ['name' => 'After'],
            100,
        );

        self::assertSame('App\\User', $event->entityType);
        self::assertSame(5, $event->entityId);
        self::assertSame(['name' => 'Before'], $event->oldValues);
        self::assertSame(['name' => 'After'], $event->newValues);
        self::assertSame(100, $event->userId);
    }

    public function testUserIdDefaultsToNull(): void
    {
        self::assertNull((new EntityUpdatedEvent('App\\User', 1, [], []))->userId);
    }
}
