# Caching and consistency

The project uses several different kinds of caching. They have different
lifetimes and invalidation rules; they must not be treated as interchangeable.

## Stores

The local Docker environment uses Redis for the default cache and a dedicated
Redis connection for rate limiting. Redis databases are separated by
connection (`default`, `cache`, and `limiter`) and the application cache prefix
is derived from `APP_NAME` unless `CACHE_PREFIX` is provided.

The database cache, file cache, array cache, Memcached, DynamoDB, Octane,
failover, and Redis stores remain available as framework configuration options.
Tests override cache stores with the array driver.

## Master reference-data cache

`UnitCache` has two layers:

- a scoped in-memory layer for the current application/request lifecycle;
- a persistent cache layer with a 24-hour TTL.

Cached data includes:

- units keyed by code;
- units grouped by `group_id`;
- base units keyed by `group_id`;
- currencies keyed by code.

Unit cache keys include the current permissions team (`system` or the
organization ID), so tenant-specific units do not leak between organizations.
Currencies currently use the global key `master:currencies:all` because the
currency model is not tenant-scoped.

After a successful unit sync, `UnitService` rewarms the relevant unit cache in
an `DB::afterCommit` callback. The cache is therefore not refreshed when the
transaction rolls back. Use `rewarmUnits()` or `rewarmCurrencies()` after
direct data maintenance.

## Request-local computation caches

`InventoryService` keeps a request-scoped stock-selection cache while processing
one transaction. It is cleared before a new operation and is never a substitute
for database locking. Stock queries use `lockForUpdate()` inside transactions
to protect concurrent deductions.

`UnitCache` and the inventory selection cache are implementation caches, not
business state. The database remains authoritative.

## Permission cache

`permissions:discover` clears Spatie's permission cache before and after
discovering permissions and synchronizing built-in roles. If roles or
permissions are modified directly, the permission registrar cache must also be
forgotten before expecting new authorization results.
