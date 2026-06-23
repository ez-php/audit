<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Audit\AuditLoggerInterface;
use EzPhp\Audit\AuditServiceProvider;
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
}
