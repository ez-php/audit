# Changelog

All notable changes to `ez-php/audit` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Added
- `AuditRecord` — immutable value object representing an audit log entry; factory methods `fromCreated()`, `fromUpdated()`, `fromDeleted()`
- `AuditLoggerInterface` — `log(AuditRecord): void` contract for audit storage backends
- `AuditLogger` — PDO-backed implementation; auto-creates the `audit_logs` table on first write
- `AuditAction` — backed enum: `CREATE`, `UPDATE`, `DELETE`
- `AuditQuery` — fluent, immutable query builder for reading the audit log; static `for()` entry point; filters by entity type, entity ID, action, user ID, and time range
- `AuditListener` — `ListenerInterface` implementation that routes `EntityCreatedEvent`, `EntityUpdatedEvent`, and `EntityDeletedEvent` to `AuditLoggerInterface`
- `EntityCreatedEvent`, `EntityUpdatedEvent`, `EntityDeletedEvent` — `EventInterface` implementations carrying entity type, entity ID, old/new values, and optional user ID
- `AuditServiceProvider` — binds `AuditLoggerInterface`, registers `AuditListener` with the event dispatcher, and initialises `AuditQuery` with the shared PDO connection
- `AuditException` for storage and query failures
