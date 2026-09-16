<?php

declare(strict_types=1);

namespace EzPhp\Audit\Console;

use DateTimeImmutable;
use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;
use EzPhp\Console\Output;
use PDO;

/**
 * Class AuditPruneCommand
 *
 * Deletes audit_logs rows older than a given cutoff date.
 *
 * Usage:
 *   ez audit:prune --before=2024-01-01
 *   ez audit:prune --days=90
 *
 * Purging is opt-in and not run automatically — invoke from a scheduled job
 * (e.g. via ez-php/scheduler) if periodic pruning is desired.
 *
 * @package EzPhp\Audit\Console
 */
final readonly class AuditPruneCommand implements CommandInterface
{
    /**
     * AuditPruneCommand Constructor
     *
     * @param PDO $pdo
     */
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'audit:prune';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Delete audit log records older than a given cutoff date';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return implode("\n", [
            'Usage: ez audit:prune --before=YYYY-MM-DD',
            '   or: ez audit:prune --days=N',
            '',
            'Options:',
            '  --before=DATE  Delete records created before this date',
            '  --days=N       Delete records older than N days from now',
        ]);
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);
        $cutoff = $this->resolveCutoff($input);

        if ($cutoff === null) {
            Output::error('Missing cutoff: pass --before=YYYY-MM-DD or --days=N.');

            return 1;
        }

        $stmt = $this->pdo->prepare('DELETE FROM audit_logs WHERE created_at < ?');
        $stmt->execute([$cutoff->format('Y-m-d H:i:s')]);

        Output::info(sprintf('Pruned %d audit log record(s) older than %s.', $stmt->rowCount(), $cutoff->format('Y-m-d')));

        return 0;
    }

    /**
     * @param Input $input
     *
     * @return DateTimeImmutable|null
     */
    private function resolveCutoff(Input $input): ?DateTimeImmutable
    {
        $before = $input->option('before');

        if ($before !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $before);

            return $parsed !== false ? $parsed : null;
        }

        $days = $input->option('days');

        if ($days !== '' && ctype_digit($days)) {
            return (new DateTimeImmutable())->modify('-' . $days . ' days');
        }

        return null;
    }
}
