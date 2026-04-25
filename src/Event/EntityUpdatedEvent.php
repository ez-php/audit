<?php

declare(strict_types=1);

namespace EzPhp\Audit\Event;

use EzPhp\Events\EventInterface;

/**
 * Class EntityUpdatedEvent
 *
 * Dispatched from a repository or application code when an entity is updated.
 * The AuditListener handles this event and writes an UPDATE record to audit_logs.
 *
 * Usage in a repository:
 *
 *   Event::dispatch(new EntityUpdatedEvent(User::class, $id, $oldValues, $newValues));
 *
 * @package EzPhp\Audit\Event
 */
final class EntityUpdatedEvent implements EventInterface
{
    /**
     * @param string               $entityType Fully-qualified class name or short identifier.
     * @param int|string           $entityId   Primary key of the updated entity.
     * @param array<string, mixed> $oldValues  Attribute values before the update.
     * @param array<string, mixed> $newValues  Attribute values after the update.
     * @param int|string|null      $userId     Identifier of the user who triggered the update.
     */
    public function __construct(
        public readonly string $entityType,
        public readonly int|string $entityId,
        public readonly array $oldValues,
        public readonly array $newValues,
        public readonly int|string|null $userId = null,
    ) {
    }
}
