# Generational cache

`GenerationalCache` caches the entries of a **group** and invalidates the
whole group with **one write**, without knowing which entries exist. The
technique is known as generational caching, versioned keys or namespace
versioning; Memcached made it common because it cannot delete a group of keys.

It suits a **computed view that is expensive to build and read often**, whose
source changes rarely and as a whole: the compiled tax profiles of an
organization are the first user.

## The problem

A set of cached entries depends on one source. When the source changes, the
whole set must go. The usual answers each have a flaw:

- **Delete every key**: you must know which keys exist, so you keep a list,
  and keeping that list right under concurrency is its own problem.
- **Flush the cache**: it also drops what belongs to other groups and modules.
- **Cache tags**: Laravel keeps the list for you, but only on some drivers
  (Redis, Memcached; not `file` or `database`). On Redis each tag keeps an
  index of its keys that fills with dead entries and needs
  `cache:prune-stale-tags`, flushing a tag walks that index, and the behaviour
  changed between Laravel versions.

## The idea: never delete, rename

Each group has one **generation key**, holding a random token:

```
tax:compiled-profile:{org}:generation        → "0193a…"
```

Every entry of the group carries that token in its key:

```
tax:compiled-profile:{org}:0193a…:{profile A}
tax:compiled-profile:{org}:0193a…:{profile B}
```

Every read follows the same order:

1. read the generation key, to know the current generation;
2. build the entry keys with it;
3. read those entries, all in one `many()` call.

To invalidate the group, write a new token:

```
tax:compiled-profile:{org}:generation        → "0194f…"
```

From then on, reads build `…:0194f…:…` keys that do not exist yet: they miss
and the entries are rebuilt. The old `…:0193a…:…` entries are still stored,
but nothing can name them any more; their TTL removes them. It is the same
principle as cache busting static files (`app.3f9c.js`): publish under a new
name instead of changing the old one.

## Usage

```php
$profiles = $generationalCache->many(
    group: "tax:compiled-profile:{$organizationId}",
    ids: $profileIds,
    ttlSeconds: 86_400,
    resolveMissing: fn (Collection $missingIds): Collection => $compiler->compile($organizationId, $missingIds),
    encode: fn (CompiledTaxProfile $profile): array => $profile->toArray(),
    decode: fn (mixed $payload): CompiledTaxProfile => CompiledTaxProfile::fromArray($payload),
);

// In the transaction that changes the source:
$generationalCache->invalidateAfterCommit("tax:compiled-profile:{$organizationId}");
```

The group name decides the invalidation scope: an organization, a user, a
record. Put the owning module in the name so groups never collide. A module
usually wraps the calls in a small class that knows its group, TTL and
encoding (see `Lahatre\Tax\Services\Engine\CompiledTaxProfileCache`).

## Why it holds

- **One write, whatever the size.** Invalidating 3 entries or 3 000 costs the
  same, and the switch is atomic: no moment where half the group is stale.
- **No index to keep.** There is no list of keys that could drift, grow or get
  out of sync.
- **Any cache store.** It only needs `get`, `add`, `many`, `putMany` and
  `forever`: Redis, Memcached, database, file, array in tests.
- **The TTL cleans up.** Orphaned entries are never read again and expire on
  their own.

## The details that matter

- **The generation key lives forever, the entries do not.** The generation is
  a tiny value written with `forever`: if it disappears, the whole group turns
  cold. Entries always get a TTL, so old generations do not stay in memory.
- **A missing generation is created with `add`, not `put`.** `add` writes
  only when the key is absent, so two requests that find no generation at the
  same time end up reading the same one. With `put`, one of them could store
  entries under a generation that has already been replaced.
- **The generation is a random token, never a counter.** If a counter is lost
  (evicted, store restarted), it restarts at 1 and reads the entries of an old
  generation 1 again. A new random token can never match an old key space.
- **Invalidate after commit.** `invalidateAfterCommit()` runs through
  `DB::afterCommit()`. Invalidating before the commit would let a concurrent
  request rebuild an entry from the old, still committed data and store it
  under the new generation, where it would stay stale until its TTL. After the
  commit that race is impossible, and a rolled-back transaction invalidates
  nothing. Outside a transaction, it runs at once.
- **One key per entry, one round trip.** Entries are read together with
  `many()`, so a request needing 5 entries makes one call, as a single big key
  would. But a request reads only what it needs, rebuilds only what is
  missing, and each write is independent: no read-modify-write of a shared
  blob where one request would overwrite another's work.
- **Request memory.** The class is bound `scoped`: entries read once in a
  request are kept in memory for the rest of it. The generation is part of the
  memory key, so a request that invalidates its own group reads the new
  generation.

## Limits

- **Coarse invalidation.** A write invalidates the whole group, even the
  entries it did not change. That fits a source written rarely and read often
  (configuration). For data written often, choose smaller groups.
- **Cold after each write.** Right after an invalidation, every read of the
  group rebuilds its entries. If rebuilding is very expensive under heavy
  traffic, protect it (a rebuild lock, or warming the cache).
- **Dead entries until their TTL.** Old generations take room until they
  expire; keep the TTL in line with the entries' size and number.
- **One extra read per call.** The generation key is read before the entries
  on every call; request memory spares the entries themselves.
- **A cache, not a source of truth.** Between the commit and the new
  generation there is a short window where an entry can still be stale. Use
  the cache to compute fast; anything that decides (an invariant, an
  authorization, an assignment) reads the database, under a lock when needed.
  The tax module assigns profiles to items from the database for that reason.
