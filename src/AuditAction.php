<?php

declare(strict_types=1);

namespace EzPhp\Audit;

/**
 * Enum AuditAction
 *
 * Represents the type of change recorded in an audit log entry.
 *
 * @package EzPhp\Audit
 */
enum AuditAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
