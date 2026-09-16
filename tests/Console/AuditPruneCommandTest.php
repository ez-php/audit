<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Audit\Console\AuditPruneCommand;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * @covers \EzPhp\Audit\Console\AuditPruneCommand
 */
#[CoversClass(AuditPruneCommand::class)]
final class AuditPruneCommandTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            'CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                action TEXT NOT NULL,
                old_values TEXT,
                new_values TEXT,
                user_id TEXT,
                created_at TEXT NOT NULL
            )'
        );

        $this->seed('2020-01-01 00:00:00');
        $this->seed('2020-06-01 00:00:00');
        $this->seed('2026-09-01 00:00:00');
    }

    private function seed(string $createdAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (entity_type, entity_id, action, user_id, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(['App\\User', '1', 'create', null, $createdAt]);
    }

    private function countRows(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM audit_logs');
        self::assertInstanceOf(\PDOStatement::class, $stmt);

        return (int) $stmt->fetchColumn();
    }

    public function test_get_name_returns_audit_prune(): void
    {
        $command = new AuditPruneCommand($this->pdo);

        self::assertSame('audit:prune', $command->getName());
    }

    public function test_deletes_records_older_than_before_option(): void
    {
        $command = new AuditPruneCommand($this->pdo);

        ob_start();
        $exitCode = $command->handle(['--before=2021-01-01']);
        ob_end_clean();

        self::assertSame(0, $exitCode);

        $remaining = $this->countRows();
        self::assertSame(1, $remaining);
    }

    public function test_deletes_records_older_than_days_option(): void
    {
        $this->pdo->exec('DELETE FROM audit_logs');
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (entity_type, entity_id, action, user_id, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(['App\\User', '1', 'create', null, (new \DateTimeImmutable('-100 days'))->format('Y-m-d H:i:s')]);
        $stmt->execute(['App\\User', '2', 'create', null, (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s')]);

        $command = new AuditPruneCommand($this->pdo);

        ob_start();
        $exitCode = $command->handle(['--days=30']);
        ob_end_clean();

        self::assertSame(0, $exitCode);

        $remaining = $this->countRows();
        self::assertSame(1, $remaining);
    }

    public function test_returns_error_when_no_cutoff_option_given(): void
    {
        $command = new AuditPruneCommand($this->pdo);

        ob_start();
        $exitCode = $command->handle([]);
        ob_end_clean();

        self::assertSame(1, $exitCode);

        $remaining = $this->countRows();
        self::assertSame(3, $remaining);
    }
}
