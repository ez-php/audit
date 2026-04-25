# ez-php/audit

Event-driven audit log module for ez-php. Records entity lifecycle changes (CREATE, UPDATE, DELETE) to a database table and exposes a fluent query API.

## Installation

```bash
composer require ez-php/audit
```

Register the service provider:

```php
// provider/modules.php
return [
    \EzPhp\Audit\AuditServiceProvider::class,
];
```

`AuditServiceProvider` requires `DatabaseInterface` and `ez-php/events` (`EventServiceProvider`) to be registered before it.

## Database Schema

The module auto-creates the `audit_logs` table on first write (`ensureTable()`). For production use, prefer running a migration:

```sql
CREATE TABLE audit_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(255) NOT NULL,
    entity_id   VARCHAR(255) NOT NULL,
    action      VARCHAR(10)  NOT NULL,  -- 'create', 'update', 'delete'
    old_values  JSON         DEFAULT NULL,
    new_values  JSON         DEFAULT NULL,
    user_id     VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME     NOT NULL,
    INDEX       idx_audit_entity  (entity_type, entity_id),
    INDEX       idx_audit_created (created_at)
);
```

## Usage

### Dispatching audit events from repositories

Audit records are created by dispatching events from repository methods:

```php
use EzPhp\Audit\Event\EntityCreatedEvent;
use EzPhp\Audit\Event\EntityUpdatedEvent;
use EzPhp\Audit\Event\EntityDeletedEvent;
use EzPhp\Events\Event;

// On create:
Event::dispatch(new EntityCreatedEvent(
    entityType: User::class,
    entityId:   $user->id(),
    newValues:  $user->toArray(),
    userId:     $currentUserId,   // optional
));

// On update:
Event::dispatch(new EntityUpdatedEvent(
    entityType: User::class,
    entityId:   $user->id(),
    oldValues:  $previousValues,
    newValues:  $user->toArray(),
    userId:     $currentUserId,
));

// On delete:
Event::dispatch(new EntityDeletedEvent(
    entityType: User::class,
    entityId:   $user->id(),
    oldValues:  $user->toArray(),
    userId:     $currentUserId,
));
```

`AuditServiceProvider` registers `AuditListener` automatically — no manual listener registration needed.

### Querying the audit trail

```php
use EzPhp\Audit\AuditAction;
use EzPhp\Audit\AuditQuery;

// All audit records for a specific entity:
$records = AuditQuery::for(User::class, $userId)->get();

// Only UPDATE records:
$records = AuditQuery::for(User::class, $userId)
    ->action(AuditAction::UPDATE)
    ->get();

// Records from the last 30 days:
$records = AuditQuery::for(User::class, $userId)
    ->since(new DateTimeImmutable('-30 days'))
    ->get();

// Records up to a date by a specific user:
$records = AuditQuery::for(User::class, $userId)
    ->until(new DateTimeImmutable('2024-12-31'))
    ->byUser($adminId)
    ->get();

// Latest record only:
$latest = AuditQuery::for(User::class, $userId)->first();

// Count:
$count = AuditQuery::for(User::class, $userId)->count();
```

All filter methods return a clone — chaining does not modify the original query.

### Programmatic logging (without events)

```php
use EzPhp\Audit\AuditLogger;
use EzPhp\Audit\AuditRecord;
use EzPhp\Audit\AuditAction;

$logger = new AuditLogger($pdo);
$logger->log(new AuditRecord(
    entityType: 'App\\Order',
    entityId:   '123',
    action:     AuditAction::UPDATE,
    oldValues:  ['status' => 'pending'],
    newValues:  ['status' => 'shipped'],
    userId:     'admin',
    createdAt:  new DateTimeImmutable(),
));
```

## Design

**Integration via events, not ORM core.** Repositories dispatch `EntityCreatedEvent`, `EntityUpdatedEvent`, and `EntityDeletedEvent`. `AuditListener` (registered by the service provider) converts them to `AuditRecord` and writes to the database. No ORM kernel changes required.

**`AuditQuery` is immutable.** All filter methods (`action()`, `since()`, `until()`, `byUser()`, `limit()`) return a clone. Re-use the same base query safely.

**`ensureTable()` for zero-config development.** `AuditLogger` auto-creates `audit_logs` on first write, adapting DDL to the PDO driver (SQLite for tests, MySQL for production). Production deployments should prefer a migration.

**No hard ORM dependency.** Depends only on `ez-php/contracts` and `ez-php/events`. Works with any persistence mechanism that dispatches the provided event types.

## API Reference

### `AuditAction` (enum)

| Case | Value |
|------|-------|
| `AuditAction::CREATE` | `'create'` |
| `AuditAction::UPDATE` | `'update'` |
| `AuditAction::DELETE` | `'delete'` |

### `AuditRecord` (value object)

| Property | Type | Description |
|----------|------|-------------|
| `entityType` | `string` | Class name or identifier |
| `entityId` | `string` | Primary key (cast to string) |
| `action` | `AuditAction` | What happened |
| `oldValues` | `array<string, mixed>` | Before-state (empty for creates) |
| `newValues` | `array<string, mixed>` | After-state (empty for deletes) |
| `userId` | `string\|null` | Who triggered the change |
| `createdAt` | `DateTimeImmutable` | When it happened |

Static factories: `AuditRecord::fromCreated()`, `::fromUpdated()`, `::fromDeleted()`.

### `AuditQuery` (fluent query builder)

| Method | Description |
|--------|-------------|
| `AuditQuery::for(class, id)` | Scope to entity; returns new query |
| `->action(AuditAction)` | Filter by action type |
| `->since(DateTimeImmutable)` | Filter by minimum created_at |
| `->until(DateTimeImmutable)` | Filter by maximum created_at |
| `->byUser(int\|string)` | Filter by user_id |
| `->limit(int)` | Limit result count |
| `->get()` | Execute; return `list<AuditRecord>` |
| `->first()` | Return latest record or null |
| `->count()` | Return total count |

### Events

| Class | When to dispatch |
|-------|-----------------|
| `EntityCreatedEvent` | Entity created |
| `EntityUpdatedEvent` | Entity updated |
| `EntityDeletedEvent` | Entity deleted |
