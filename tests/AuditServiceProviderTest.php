<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Audit\AuditLoggerInterface;
use EzPhp\Audit\AuditServiceProvider;
use EzPhp\Audit\Console\AuditPruneCommand;
use EzPhp\Contracts\CommandRegistryInterface;
use EzPhp\Contracts\ContainerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: AuditServiceProvider registers and boots in a minimal container
 * context without error.
 *
 * With no DatabaseInterface or EventDispatcher bound, register() binds the
 * logger lazily and boot() degrades gracefully (audit disabled) rather than
 * throwing.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 */
#[CoversClass(AuditServiceProvider::class)]
final class AuditServiceProviderTest extends TestCase
{
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
