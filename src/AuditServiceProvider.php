<?php

declare(strict_types=1);

namespace EzPhp\Audit;

use EzPhp\Audit\Console\AuditPruneCommand;
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
 * boot() checks both with has(): without DatabaseInterface audit is disabled,
 * without EventDispatcher only the listener is skipped (AuditQuery still works).
 * Nothing else is caught — an unreachable database or a broken binding surfaces
 * instead of silently switching auditing off.
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

        $this->app->bind(AuditPruneCommand::class, function (ContainerInterface $app): AuditPruneCommand {
            $db = $app->make(DatabaseInterface::class);

            return new AuditPruneCommand($db->getPdo());
        });
    }

    /**
     * Initialise AuditQuery, wire the AuditListener, and (when running inside
     * the ez-php Application) auto-register audit:prune.
     *
     * Skips what a missing binding makes impossible (see the class doc); every
     * other error propagates.
     */
    public function boot(): void
    {
        $this->registerCommand(AuditPruneCommand::class);

        if (!$this->app->has(DatabaseInterface::class)) {
            return;
        }

        AuditQuery::setPdo($this->app->make(DatabaseInterface::class)->getPdo());

        if (!$this->app->has(EventDispatcher::class)) {
            return;
        }

        $listener = new AuditListener($this->app->make(AuditLoggerInterface::class));
        $dispatcher = $this->app->make(EventDispatcher::class);
        $dispatcher->listen(EntityCreatedEvent::class, $listener);
        $dispatcher->listen(EntityUpdatedEvent::class, $listener);
        $dispatcher->listen(EntityDeletedEvent::class, $listener);
    }
}
