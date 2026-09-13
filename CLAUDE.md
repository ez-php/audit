# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are the one carve-out, since they exist specifically to be extended.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum` and
`opcache` → `OPCache` are existing exceptions the guess gets wrong).

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks all ~40
  `CLAUDE.md` copies as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| **next free** | **3311** | **6383** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project is the one exception, since it has no host/container split and uses `REDIS_PORT` for both.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/audit

## Source structure

```
src/
├── AuditAction.php              — enum: CREATE, UPDATE, DELETE
├── AuditException.php           — base exception for the module
├── AuditLoggerInterface.php     — log(AuditRecord): void contract
├── AuditLogger.php              — PDO-backed implementation; ensureTable() auto-creates audit_logs
├── AuditRecord.php              — immutable value object: entityType, entityId, action, old/newValues, userId, createdAt
├── AuditListener.php            — ListenerInterface: routes entity lifecycle events to AuditLoggerInterface
├── AuditQuery.php               — fluent, immutable query builder; static for() entry point backed by static PDO
├── AuditServiceProvider.php     — binds AuditLoggerInterface; registers AuditListener; initialises AuditQuery
└── Event/
    ├── EntityCreatedEvent.php   — EventInterface: entityType, entityId, newValues, userId
    ├── EntityUpdatedEvent.php   — EventInterface: entityType, entityId, oldValues, newValues, userId
    └── EntityDeletedEvent.php   — EventInterface: entityType, entityId, oldValues, userId

tests/
├── TestCase.php
├── AuditRecordTest.php          — covers factory methods fromCreated/fromUpdated/fromDeleted
├── AuditLoggerTest.php          — SQLite :memory:; covers table creation, all three actions, JSON encoding
├── AuditQueryTest.php           — SQLite :memory:; covers all filters, ordering, count, first, cloning
├── AuditListenerTest.php        — SpyAuditLogger; covers all three event types and unknown event pass-through
└── Event/
    ├── EntityCreatedEventTest.php
    ├── EntityUpdatedEventTest.php
    └── EntityDeletedEventTest.php
```

## Key classes and responsibilities

### `AuditAction` (`src/AuditAction.php`)

String-backed enum with three cases: `CREATE` (`'create'`), `UPDATE` (`'update'`), `DELETE` (`'delete'`). Used as the `action` field on `AuditRecord` and as a filter parameter in `AuditQuery`.

---

### `AuditRecord` (`src/AuditRecord.php`)

Immutable value object representing a single audit log entry. Constructed directly or via three static factories:

- `AuditRecord::fromCreated(EntityCreatedEvent)` — action: CREATE, oldValues: []
- `AuditRecord::fromUpdated(EntityUpdatedEvent)` — action: UPDATE
- `AuditRecord::fromDeleted(EntityDeletedEvent)` — action: DELETE, newValues: []

All factories cast `entityId` and `userId` to `string` / `string|null` for consistent storage.

---

### `AuditLoggerInterface` / `AuditLogger` (`src/AuditLoggerInterface.php`, `src/AuditLogger.php`)

`AuditLoggerInterface` is a single-method contract: `log(AuditRecord): void`.

`AuditLogger` writes to the `audit_logs` table via PDO. `old_values` and `new_values` are JSON-encoded strings; empty arrays are stored as `NULL`. `ensureTable()` creates the table if not present, using driver-aware DDL (SQLite for tests, MySQL for production). `ensureTable()` is called lazily on first `log()` and is idempotent (guarded by a boolean flag).

---

### `AuditListener` (`src/AuditListener.php`)

Implements `EzPhp\Events\ListenerInterface`. Registered by `AuditServiceProvider` for all three event types. The `handle()` method dispatches on type with `instanceof` and calls the appropriate `AuditRecord` factory. Unknown event types are silently ignored.

---

### Events (`src/Event/`)

Three `EventInterface` implementations that application repositories dispatch:

| Class | Fields |
|-------|--------|
| `EntityCreatedEvent` | `entityType`, `entityId`, `newValues`, `userId` (optional) |
| `EntityUpdatedEvent` | `entityType`, `entityId`, `oldValues`, `newValues`, `userId` (optional) |
| `EntityDeletedEvent` | `entityType`, `entityId`, `oldValues`, `userId` (optional) |

All fields are `readonly`. The `userId` defaults to `null`.

---

### `AuditQuery` (`src/AuditQuery.php`)

Fluent, immutable query builder scoped to a single entity (`entityType + entityId`).

**Entry point:** `AuditQuery::for(string $entityType, int|string $entityId): self` — requires the static PDO to be set first via `AuditQuery::setPdo()` (done by `AuditServiceProvider::boot()`). Throws `RuntimeException` when called before initialisation (fail-fast).

**Filters (all return a clone):** `action()`, `since()`, `until()`, `byUser()`, `limit()`.

**Terminal methods:** `get(): list<AuditRecord>`, `first(): ?AuditRecord`, `count(): int`. Results are ordered by `created_at DESC`.

`AuditQuery::resetPdo()` is provided for test tearDown to prevent static state leaking.

---

### `AuditServiceProvider` (`src/AuditServiceProvider.php`)

`register()` binds `AuditLoggerInterface` → `AuditLogger` (requires `DatabaseInterface` in the container).

`boot()` does three things inside a single `try/catch \Throwable`:
1. Calls `AuditQuery::setPdo($db->getPdo())` to initialise the static query factory.
2. Creates and registers an `AuditListener` for `EntityCreatedEvent`, `EntityUpdatedEvent`, and `EntityDeletedEvent` via `EventDispatcher::listen()`.
3. If `DatabaseInterface` or `EventDispatcher` is missing, the entire `boot()` step is skipped — audit is disabled without crashing the application.

---

## Design decisions and constraints

- **Integration via events, not ORM core.** The audit module defines the events and the listener. Repositories dispatch events when persistence operations occur. No changes to the ORM kernel are required. Application code controls which entities are audited by choosing which repositories dispatch events.
- **`AuditQuery` uses static initialisation (like `Flag` and `AuditQuery::for()`).** The static factory pattern follows the same convention as `Flag::enabled()` and `AuditQuery::for()`: a `setPdo()` / `resetPdo()` pair, fail-fast `RuntimeException` when uninitialised, and test-friendly reset. This avoids injecting `AuditQuery` through the full call stack when used in controllers.
- **Immutable `AuditQuery` — filter methods return clones.** The same base query can be re-used with different filters without side effects. This matches the `Request` clone-based wither pattern used elsewhere in the framework.
- **`ensureTable()` uses driver-aware DDL.** MySQL uses `JSON` columns and `INDEX` declarations; SQLite uses `TEXT` with `INTEGER PRIMARY KEY AUTOINCREMENT`. The branching is a one-time check per `AuditLogger` instance. Production deployments should prefer a proper migration.
- **`old_values` / `new_values` stored as JSON strings (TEXT).** JSON columns behave as TEXT in SQLite and as optimised JSON in MySQL. Using TEXT avoids cross-database compatibility issues while preserving full JSON query capability in MySQL.
- **`AuditListener` is registered per event class.** `EventDispatcher::listen()` is called three times (once per event type). Each call registers the same `AuditListener` instance. This is explicit and allows individual event types to be un-listened if needed.
- **No `Auditable` interface or trait on entities.** Requiring entities to implement an `Auditable` interface would couple application models to the audit module. Instead, auditing is opt-in per repository. This keeps entity classes clean and the coupling minimal.

---

## Testing approach

No external infrastructure required. All tests run in-process:

- `AuditRecordTest` — pure unit; tests all three factories, property values, type casting
- `AuditLoggerTest` — SQLite `:memory:` via PDO; covers table creation, JSON encoding, NULL handling, all actions
- `AuditQueryTest` — SQLite `:memory:`; covers all filter combinations, ordering, count, first, clone isolation, uninitialised throw
- `AuditListenerTest` — `SpyAuditLogger` (file-scope named class); covers all three event types and unknown event pass-through
- `EntityCreatedEventTest`, `EntityUpdatedEventTest`, `EntityDeletedEventTest` — pure unit; cover `EventInterface` contract, property storage, defaults

`SpyAuditLogger` is a file-scope named class (not anonymous) to avoid PHPStan's `property.onlyWritten` check on the public `$records` array.

`AuditQuery::resetPdo()` is called in `tearDown()` of `AuditQueryTest` to prevent static state leaking between test classes.

---

## What does NOT belong in this module

| Concern | Where it belongs |
|---------|-----------------|
| Admin UI for viewing audit logs | Application layer |
| Audit log pagination via HTTP | Application layer (query `AuditQuery` in controllers) |
| Soft-delete tracking | `ez-php/orm` soft-delete feature |
| Field-level change diffing | Application layer — compute diff before dispatching `EntityUpdatedEvent` |
| Exporting audit logs to CSV / PDF | Application layer |
| Purging / archiving old audit logs | Application layer (scheduled job via `ez-php/scheduler`) |
| Per-field encryption of sensitive audit values | Application layer |
| Real-time audit streaming | `ez-php/broadcast` |
