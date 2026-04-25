<?php

declare(strict_types=1);

namespace EzPhp\Audit\Event;

use EzPhp\Events\EventInterface;

/**
 * Class EntityDeletedEvent
 *
 * Dispatched from a repository or application code when an entity is deleted.
 * The AuditListener handles this event and writes a DELETE record to audit_logs.
 *
 * Usage in a repository:
 *
 *   Event::dispatch(new EntityDeletedEvent(User::class, $id, $entity->toArray()));
 *
 * @package EzPhp\Audit\Event
 */
final class EntityDeletedEvent implements EventInterface
{
    /**
     * @param string               $entityType Fully-qualified class name or short identifier.
     * @param int|string           $entityId   Primary key of the deleted entity.
     * @param array<string, mixed> $oldValues  Attribute values at the time of deletion.
     * @param int|string|null      $userId     Identifier of the user who triggered the delete.
     */
    public function __construct(
        public readonly string $entityType,
        public readonly int|string $entityId,
        public readonly array $oldValues,
        public readonly int|string|null $userId = null,
    ) {
    }
}
