<?php

declare(strict_types=1);

namespace EzPhp\Audit;

use DateTimeImmutable;
use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;

/**
 * Class AuditRecord
 *
 * Immutable value object representing a single audit log entry.
 * Created from entity lifecycle events via the static factory methods,
 * or constructed directly for programmatic logging.
 *
 * @package EzPhp\Audit
 */
final class AuditRecord
{
    /**
     * @param string               $entityType Fully-qualified class name or short identifier of the entity.
     * @param string               $entityId   String representation of the entity primary key.
     * @param AuditAction          $action     The action performed (create, update, delete).
     * @param array<string, mixed> $oldValues  Attribute values before the change (empty for creates).
     * @param array<string, mixed> $newValues  Attribute values after the change (empty for deletes).
     * @param string|null          $userId     Identifier of the authenticated user who made the change.
     * @param DateTimeImmutable    $createdAt  When the change occurred.
     */
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly AuditAction $action,
        public readonly array $oldValues,
        public readonly array $newValues,
        public readonly ?string $userId,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Construct an AuditRecord from an EntityCreatedEvent.
     */
    public static function fromCreated(EntityCreatedEvent $event): self
    {
        return new self(
            entityType: $event->entityType,
            entityId: (string) $event->entityId,
            action: AuditAction::CREATE,
            oldValues: [],
            newValues: $event->newValues,
            userId: $event->userId !== null ? (string) $event->userId : null,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Construct an AuditRecord from an EntityUpdatedEvent.
     */
    public static function fromUpdated(EntityUpdatedEvent $event): self
    {
        return new self(
            entityType: $event->entityType,
            entityId: (string) $event->entityId,
            action: AuditAction::UPDATE,
            oldValues: $event->oldValues,
            newValues: $event->newValues,
            userId: $event->userId !== null ? (string) $event->userId : null,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * Construct an AuditRecord from an EntityDeletedEvent.
     */
    public static function fromDeleted(EntityDeletedEvent $event): self
    {
        return new self(
            entityType: $event->entityType,
            entityId: (string) $event->entityId,
            action: AuditAction::DELETE,
            oldValues: $event->oldValues,
            newValues: [],
            userId: $event->userId !== null ? (string) $event->userId : null,
            createdAt: new DateTimeImmutable(),
        );
    }
}
