<?php

declare(strict_types=1);

namespace EzPhp\Audit;

/**
 * Interface AuditLoggerInterface
 *
 * Contract for writing audit records to a persistent store.
 *
 * @package EzPhp\Audit
 */
interface AuditLoggerInterface
{
    /**
     * Persist an audit record.
     *
     * @throws AuditException If the record cannot be written.
     */
    public function log(AuditRecord $record): void;
}
