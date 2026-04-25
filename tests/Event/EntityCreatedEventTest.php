<?php

declare(strict_types=1);

namespace Tests\Event;

use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Events\EventInterface;
use Tests\TestCase;

/**
 * @covers \EzPhp\Audit\Event\EntityCreatedEvent
 */
final class EntityCreatedEventTest extends TestCase
{
    public function testImplementsEventInterface(): void
    {
        self::assertInstanceOf(EventInterface::class, new EntityCreatedEvent('App\\User', 1, []));
    }

    public function testPropertiesAreStoredCorrectly(): void
    {
        $event = new EntityCreatedEvent('App\\User', 42, ['name' => 'Alice', 'email' => 'a@b.com'], 'user-1');

        self::assertSame('App\\User', $event->entityType);
        self::assertSame(42, $event->entityId);
        self::assertSame(['name' => 'Alice', 'email' => 'a@b.com'], $event->newValues);
        self::assertSame('user-1', $event->userId);
    }

    public function testUserIdDefaultsToNull(): void
    {
        self::assertNull((new EntityCreatedEvent('App\\User', 1, []))->userId);
    }

    public function testAcceptsStringEntityId(): void
    {
        $event = new EntityCreatedEvent('App\\Post', 'slug-abc', []);

        self::assertSame('slug-abc', $event->entityId);
    }
}
