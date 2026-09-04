---
paths:
  - app/Support/**
  - app/Http/Requests/**
  - app-modules/*/src/**
  - app-modules/*/database/**
  - database/**
---

# Persistence and Tenancy

This rule applies to every application-layer database query, not only to SQL
written in Services. It includes queries in Assertions, Rules, Form Requests,
Models, Jobs, Support classes, and other application code.

## Query Boundaries

- Make the tenant boundary of every read and write locally provable. Constrain by `organization_id`, apply an explicit system-plus-tenant rule, or query through an already authorized and constrained parent.
- An Eloquent query may rely on `SoftDeletes` only when the queried model actually uses the trait and the default non-deleted behavior is intended. Raw SQL, joins, aggregates, subqueries, and `DB::table()` calls must add every relevant `deleted_at` boundary explicitly.
- UUIDs are identifiers, not authorization boundaries.
- Cursor pagination must use deterministic ordering. Whitelist sort fields in Data, append a unique tie-breaker when needed, and prefer `stableCursorPaginate()` for the standard `sort_by`, `sort_order`, `per_page`, and `cursor` filter quartet.

## Service Query Policy

- Never issue a query or trigger lazy loading in a loop when the required rows can be fetched in one bulk lookup. Extract unique identifiers, query once, and key the result for in-memory matching.
- Prefer bulk `insert`, `upsert`, `update`, `delete`, relation `sync`, or chunked processing when they preserve readability and domain hooks are not required per model.
- Select only the columns and relations needed by the operation. Use eager loading to prevent N+1 reads and chunks or lazy cursors when a complete result set would be unsafe in memory.
- Align high-value query predicates and ordering with existing indexes. Add or change indexes through migrations, including partial, composite, tenant-aware, or soft-delete-aware indexes when appropriate.
- PostgreSQL-specific SQL, expressions, partial indexes, CTEs, window functions, or locking may be used when they provide a clear atomicity or performance benefit. Keep bindings parameterized, preserve tenant and soft-delete boundaries, and cover the behavior with tests.
- For non-obvious performance work, validate the expected gain with the query shape, production-like cardinality, or `EXPLAIN`/`EXPLAIN ANALYZE`; do not add database-specific complexity for a hypothetical micro-optimization.
- Prevent check-then-write races with database constraints, atomic statements, or row locks. Keep the protecting check and write in the same transaction.
- Sort inputs come from a Data whitelist, never directly from request values. For custom cursor ordering, write the explicit `orderBy()` chain and finish with `cursorPaginate()`.

## Models

- Declare the table name explicitly and limit `$fillable` to writable fields.
- Cast all meaningful business columns explicitly. Cast `id` and foreign IDs to
  `string`, booleans to `boolean`, enums to their enum class, timestamps to
  `immutable_datetime`, and civil dates with a date-only cast.
- Type every relationship. Use `Attribute` for accessors and mutators.
- Keep model organization predictable: traits, table and key metadata, fillable fields, casts, accessors/mutators, relationships, then scopes.

## Migrations

- Prefix business tables with the owning module name. Use `foreignUuid()` and `uuidMorphs()` where appropriate.
- Index foreign keys and enforce logical uniqueness in the database, including tenant and soft-delete conditions when they are part of identity.
- Treat every same-prefix `<name>_type` and `<name>_id` pair as a polymorphic
  schema contract. Keep the two columns adjacent, in either order, in at least
  one composite index; tenant or domain identity columns may precede the pair.
- Use `jsonb` for structured data and `text` for free text.
- Persist stock and inventory quantities in base units as PostgreSQL
  `bigint`, with a positive check and an explicit application-level maximum.
  Any exception must be documented in `config/model-integrity.php` and covered
  by the database integrity test.
- Apply production corrections through new migrations.
- Mandatory production reference data may be introduced by a migration; development and demo data belongs in seeders.

## Schema Audits

- When reviewing the current database structure, inspect the application
  database through Docker before relying on migration history:
  `docker compose exec -T app php artisan migrate:status`,
  `docker compose exec -T app php artisan db:show --counts --views`, and
  `docker compose exec -T app php artisan db:table <table>`.
- Treat applied migrations and the live database schema as separate evidence:
  use Artisan database commands to establish what exists now, and migration
  files to explain how it got there or where a correction belongs.
- Do not infer a current column, index, foreign key, or constraint from an old
  migration, a migration `down()` method, model PHPDoc, or an un-applied
  migration.

## Factories and Seeders

- Factories generate coherent test states and must resolve to their module models.
- Seeders are for development and demo data. Keep them idempotent, normally with `firstOrCreate()`, and do not put production-critical data in them.

## References

- `.ai/reference-examples/PersistenceQueryService.php.example` is the compact generation example for tenant-scoped queries, deterministic cursor ordering, bulk evidence loading, raw aggregate soft-delete boundaries, and a lock-protected check-then-write.
- `.ai/reference-examples/OrderService.php.example` shows a tenant-constrained bulk lookup, service-owned transaction, and evidence passed to an Assertion.
- `app-modules/inventory/src/Services/InventoryQueryService.php` shows explicit organization boundaries, PostgreSQL aggregate queries, eager loading, bulk resolution, and deterministic cursor pagination.
- `app-modules/inventory/src/Services/InventoryService.php` shows transaction-scoped inventory orchestration, batched reference resolution, and protected mutation steps.
- `app-modules/inventory/database/migrations/2026_07_17_160000_add_idempotency_to_inventory_transactions.php` shows a database constraint used to make retried operations safe.
