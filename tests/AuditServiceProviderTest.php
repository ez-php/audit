<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Audit\AuditAction;
use EzPhp\Audit\AuditListener;
use EzPhp\Audit\AuditLogger;
use EzPhp\Audit\AuditLoggerInterface;
use EzPhp\Audit\AuditQuery;
use EzPhp\Audit\AuditRecord;
use EzPhp\Audit\AuditServiceProvider;
use EzPhp\Audit\Console\AuditPruneCommand;
use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;
use EzPhp\Contracts\CommandRegistryInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Events\EventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\AuditSqliteDatabase;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: AuditServiceProvider registers and boots in a minimal container
 * context without error.
 *
 * With no DatabaseInterface or EventDispatcher bound, register() binds the
 * logger lazily and boot() skips the wiring (audit disabled) rather than
 * throwing. With both bound, entity events end up in audit_logs; any other
 * failure while wiring surfaces instead of silently disabling auditing.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 * @uses \Tests\Support\AuditSqliteDatabase
 */
#[CoversClass(AuditServiceProvider::class)]
#[UsesClass(AuditLogger::class)]
#[UsesClass(AuditListener::class)]
#[UsesClass(AuditQuery::class)]
#[UsesClass(AuditRecord::class)]
#[UsesClass(AuditAction::class)]
#[UsesClass(EntityCreatedEvent::class)]
#[UsesClass(EntityUpdatedEvent::class)]
#[UsesClass(EntityDeletedEvent::class)]
#[UsesClass(EventDispatcher::class)]
final class AuditServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        AuditQuery::resetPdo();
        parent::tearDown();
    }

    public function test_boot_wires_entity_events_into_the_audit_log(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $container->instance(DatabaseInterface::class, new AuditSqliteDatabase());
        $dispatcher = new EventDispatcher();
        $container->instance(EventDispatcher::class, $dispatcher);
        $provider = new AuditServiceProvider($container);

        $provider->register();
        $provider->boot();

        $dispatcher->dispatch(new EntityCreatedEvent('App\\Entities\\Post', 42, ['title' => 'Hello'], 7));

        $record = AuditQuery::for('App\\Entities\\Post', 42)->first();
        $this->assertNotNull($record);
        $this->assertSame(AuditAction::CREATE, $record->action);
        $this->assertSame(['title' => 'Hello'], $record->newValues);
        $this->assertSame('7', $record->userId);
    }

    public function test_boot_initialises_audit_query_without_an_event_dispatcher(): void
    {
        $db = new AuditSqliteDatabase();
        $container = new FakeContainer(new FakeConfig([]));
        $container->instance(DatabaseInterface::class, $db);
        $provider = new AuditServiceProvider($container);

        $provider->register();
        $provider->boot();

        (new AuditLogger($db->getPdo()))->ensureTable();

        $this->assertSame(0, AuditQuery::for('App\\Entities\\Post', 42)->count());
    }

    public function test_boot_surfaces_a_failing_database_instead_of_disabling_audit(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $container->bind(DatabaseInterface::class, static function (): DatabaseInterface {
            throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
        });
        $container->instance(EventDispatcher::class, new EventDispatcher());
        $provider = new AuditServiceProvider($container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        $provider->boot();
    }

    public function test_register_binds_audit_logger(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new AuditServiceProvider($container);

        $provider->register();

        $this->assertTrue($container->wasBound(AuditLoggerInterface::class));
    }

    public function test_boot_does_not_throw_without_database(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new AuditServiceProvider($container);

        $provider->register();
        $provider->boot();

        // boot() swallows the missing-DatabaseInterface error; reaching here means no throw.
        $this->assertTrue($container->wasBound(AuditLoggerInterface::class));
    }

    public function test_register_binds_audit_prune_command(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new AuditServiceProvider($container);

        $provider->register();

        $this->assertTrue($container->wasBound(AuditPruneCommand::class));
    }

    public function test_boot_auto_registers_audit_prune_command_when_registry_available(): void
    {
        $registry = new class () implements ContainerInterface, CommandRegistryInterface {
            /** @var list<class-string> */
            private array $commands = [];

            public function registerCommand(string $commandClass): static
            {
                $this->commands[] = $commandClass;

                return $this;
            }

            /**
             * @return list<class-string>
             */
            public function getCommands(): array
            {
                return $this->commands;
            }

            public function bind(string $abstract, string|callable|null $factory = null): static
            {
                return $this;
            }

            public function make(string $abstract): mixed
            {
                throw new \RuntimeException('not implemented in test stub');
            }

            public function has(string $abstract): bool
            {
                return false;
            }

            public function instance(string $abstract, object $instance): void
            {
            }
        };

        (new AuditServiceProvider($registry))->boot();

        $this->assertContains(AuditPruneCommand::class, $registry->getCommands());
    }
}
