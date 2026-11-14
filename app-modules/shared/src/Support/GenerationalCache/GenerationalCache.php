<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support\GenerationalCache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Caches the entries of a group under the group's current generation, so the
 * whole group is invalidated by writing a new generation instead of deleting
 * keys. Entries are kept in request memory, then in the application cache.
 * See README.md next to this class.
 */
final class GenerationalCache
{
    /** @var array<string, mixed> decoded entries of this request, keyed by cache key */
    private array $memory = [];

    public function __construct(
        private readonly Repository $cache,
    ) {}

    /**
     * Returns the entries of the given ids, resolving only the ones missing
     * from the cache and storing them under the current generation.
     *
     * @template TValue
     *
     * @param  string  $group  the invalidation group, such as "tax:compiled-profile:{organization id}"
     * @param  Collection<int, string>  $ids
     * @param  Closure(Collection<int, string>): Collection<string, TValue>  $resolveMissing  builds the missing entries, keyed by id; an id it leaves out is not cached
     * @param  Closure(TValue): mixed  $encode  turns an entry into a value the cache store can keep
     * @param  Closure(mixed): TValue  $decode  turns a stored value back into an entry
     * @return Collection<string, TValue> keyed by id; ids nobody could resolve are absent
     */
    public function many(string $group, Collection $ids, int $ttlSeconds, Closure $resolveMissing, Closure $encode, Closure $decode): Collection
    {
        $generation = $this->generation($group);
        $keys = $ids->unique()->mapWithKeys(fn (string $id): array => [$id => "{$group}:{$generation}:{$id}"]);

        $found = $keys
            ->filter(fn (string $key): bool => array_key_exists($key, $this->memory))
            ->map(fn (string $key): mixed => $this->memory[$key]);
        $remoteKeys = $keys->diffKeys($found);

        if ($remoteKeys->isNotEmpty()) {
            $stored = collect($this->cache->many($remoteKeys->values()->all()));
            $found = $found->union($remoteKeys
                ->map(fn (string $key): mixed => $stored->get($key))
                ->reject(fn (mixed $value): bool => $value === null)
                ->map(fn (mixed $value): mixed => $decode($value)));
        }

        $missingIds = $keys->keys()->diff($found->keys())->values();

        if ($missingIds->isNotEmpty()) {
            $resolved = $resolveMissing($missingIds)->only($missingIds->all());

            if ($resolved->isNotEmpty()) {
                $this->cache->putMany(
                    $resolved->mapWithKeys(fn (mixed $entry, string $id): array => [$keys[$id] => $encode($entry)])->all(),
                    $ttlSeconds,
                );
                $found = $found->union($resolved);
            }
        }

        foreach ($found as $id => $entry) {
            $this->memory[$keys[$id]] = $entry;
        }

        return $found;
    }

    /**
     * Starts a new generation once the current transaction commits, at once
     * without a transaction, and never when it rolls back.
     */
    public function invalidateAfterCommit(string $group): void
    {
        DB::afterCommit(function () use ($group): void {
            $this->cache->forever($this->generationKey($group), $this->newGeneration());
        });
    }

    private function generation(string $group): string
    {
        $key = $this->generationKey($group);
        $generation = $this->cache->get($key);

        if (is_string($generation)) {
            return $generation;
        }

        // add() writes only when the key is absent, so concurrent requests agree on one generation.
        $this->cache->add($key, $this->newGeneration());

        return (string) $this->cache->get($key);
    }

    /** A random token, never a counter: a lost counter restarting at 1 would bring old entries back. */
    private function newGeneration(): string
    {
        return (string) Str::uuid7();
    }

    private function generationKey(string $group): string
    {
        return "{$group}:generation";
    }
}
