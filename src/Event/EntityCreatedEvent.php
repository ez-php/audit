<?php

declare(strict_types=1);

namespace EzPhp\Audit\Event;

use EzPhp\Events\EventInterface;

/**
 * Class EntityCreatedEvent
 *
 * Dispatched from a repository or application code when an entity is created.
 * The AuditListener handles this event and writes a CREATE record to audit_logs.
 *
 * Usage in a repository:
 *
 *   Event::dispatch(new EntityCreatedEvent(User::class, $user->id(), $user->toArray()));
 *
 * @package EzPhp\Audit\Event
 */
final class EntityCreatedEvent implements EventInterface
{
    /**
     * @param string               $entityType Fully-qualified class name or short identifier.
     * @param int|string           $entityId   Primary key of the created entity.
     * @param array<string, mixed> $newValues  Attribute values of the new entity.
     * @param int|string|null      $userId     Identifier of the user who triggered the create.
     */
    public function __construct(
        public readonly string $entityType,
        public readonly int|string $entityId,
        public readonly array $newValues,
        public readonly int|string|null $userId = null,
    ) {
    }
}
