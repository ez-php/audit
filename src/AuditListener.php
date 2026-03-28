<?php

declare(strict_types=1);

namespace EzPhp\Audit;

use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;
use EzPhp\Events\EventInterface;
use EzPhp\Events\ListenerInterface;

/**
 * Class AuditListener
 *
 * Handles EntityCreatedEvent, EntityUpdatedEvent, and EntityDeletedEvent by
 * converting each to an AuditRecord and delegating to AuditLoggerInterface.
 *
 * Registration in AuditServiceProvider::boot():
 *
 *   $dispatcher->listen(EntityCreatedEvent::class, $listener);
 *   $dispatcher->listen(EntityUpdatedEvent::class, $listener);
 *   $dispatcher->listen(EntityDeletedEvent::class, $listener);
 *
 * @package EzPhp\Audit
 */
final class AuditListener implements ListenerInterface
{
    /**
     * @param AuditLoggerInterface $logger Logger to persist audit records.
     */
    public function __construct(private readonly AuditLoggerInterface $logger)
    {
    }

    /**
     * Convert the incoming entity lifecycle event to an AuditRecord and log it.
     *
     * Events that are not one of the three expected types are silently ignored —
     * this should not occur in practice since the listener is registered per
     * event class via EventDispatcher::listen().
     */
    public function handle(EventInterface $event): void
    {
        if ($event instanceof EntityCreatedEvent) {
            $this->logger->log(AuditRecord::fromCreated($event));
        } elseif ($event instanceof EntityUpdatedEvent) {
            $this->logger->log(AuditRecord::fromUpdated($event));
        } elseif ($event instanceof EntityDeletedEvent) {
            $this->logger->log(AuditRecord::fromDeleted($event));
        }
    }
}
