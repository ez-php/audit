<?php

declare(strict_types=1);

namespace EzPhp\Audit;

use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Events\EventDispatcher;

/**
 * Class AuditServiceProvider
 *
 * Registers the AuditLoggerInterface binding and wires the AuditListener
 * for the three entity lifecycle events. Also initialises AuditQuery with the
 * PDO connection so static for() calls work throughout the application.
 *
 * Required dependencies (both must be bound):
 * - DatabaseInterface — for AuditLogger and AuditQuery
 * - EventDispatcher   — for listener registration (from ez-php/events)
 *
 * Both are resolved in boot() inside a single try/catch so that missing
 * bindings degrade gracefully (audit is disabled, no exception thrown).
 *
 * @package EzPhp\Audit
 */
final class AuditServiceProvider extends ServiceProvider
{
    /**
     * Bind AuditLoggerInterface to the PDO-backed AuditLogger.
     */
    public function register(): void
    {
        $this->app->bind(AuditLoggerInterface::class, function (ContainerInterface $app): AuditLoggerInterface {
            $db = $app->make(DatabaseInterface::class);

            return new AuditLogger($db->getPdo());
        });
    }

    /**
     * Wire the AuditListener and initialise AuditQuery.
     *
     * Uses try/catch so the module degrades gracefully when DatabaseInterface
     * or EventDispatcher are not bound in the container.
     */
    public function boot(): void
    {
        try {
            $db = $this->app->make(DatabaseInterface::class);
            AuditQuery::setPdo($db->getPdo());

            $logger = $this->app->make(AuditLoggerInterface::class);
            $listener = new AuditListener($logger);

            $dispatcher = $this->app->make(EventDispatcher::class);
            $dispatcher->listen(EntityCreatedEvent::class, $listener);
            $dispatcher->listen(EntityUpdatedEvent::class, $listener);
            $dispatcher->listen(EntityDeletedEvent::class, $listener);
        } catch (\Throwable) {
            // DatabaseInterface or EventDispatcher not bound — audit disabled
        }
    }
}
