<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Two-level cache of compiled profiles (request memory, then the application
 * cache). Keys carry a per-organization configuration version token that
 * every configuration write replaces after commit, so a stale entry is never
 * read again once the change is visible. The token is written, never
 * incremented, so invalidation works on every cache store, and a lost token
 * is replaced by a fresh one instead of falling back to an old key space.
 * The database stays authoritative.
 */
final class CompiledTaxProfileCache
{
    private const int TTL_SECONDS = 86_400;

    /** @var array<string, CompiledTaxProfile> */
    private array $memory = [];

    public function __construct(
        private readonly Repository $cache,
    ) {}

    /**
     * @param  Collection<int, string>  $profileIds
     * @param  Closure(Collection<int, string>): Collection<string, CompiledTaxProfile>  $compile  compiles the ids missing from the cache
     * @return Collection<string, CompiledTaxProfile> keyed by profile id; unknown ids are absent
     */
    public function many(string $organizationId, Collection $profileIds, Closure $compile): Collection
    {
        $version = $this->version($organizationId);
        $keys = $profileIds->unique()->mapWithKeys(fn (string $profileId): array => [
            $profileId => "tax:compiled-profile:{$organizationId}:{$version}:{$profileId}",
        ]);

        $found = $keys
            ->map(fn (string $key): ?CompiledTaxProfile => $this->memory[$key] ?? null)
            ->filter();
        $remoteKeys = $keys->diffKeys($found);

        if ($remoteKeys->isNotEmpty()) {
            $remote = collect($this->cache->many($remoteKeys->values()->all()));
            $found = $found->union($remoteKeys
                ->map(fn (string $key): mixed => $remote->get($key))
                ->filter(fn (mixed $payload): bool => is_array($payload))
                ->map(fn (array $payload): CompiledTaxProfile => CompiledTaxProfile::fromArray($payload)));
        }

        $missingIds = $keys->keys()->diff($found->keys())->values();

        if ($missingIds->isNotEmpty() && ($compiled = $compile($missingIds))->isNotEmpty()) {
            $this->cache->putMany(
                $compiled->mapWithKeys(fn (CompiledTaxProfile $profile, string $profileId): array => [
                    $keys[$profileId] => $profile->toArray(),
                ])->all(),
                self::TTL_SECONDS,
            );
            $found = $found->union($compiled);
        }

        foreach ($found as $profileId => $profile) {
            $this->memory[$keys[$profileId]] = $profile;
        }

        return $found;
    }

    public function invalidateAfterCommit(string $organizationId): void
    {
        DB::afterCommit(function () use ($organizationId): void {
            $this->cache->forever($this->versionKey($organizationId), $this->newVersion());
        });
    }

    private function version(string $organizationId): string
    {
        $key = $this->versionKey($organizationId);
        $version = $this->cache->get($key);

        if (is_string($version)) {
            return $version;
        }

        $this->cache->add($key, $this->newVersion());

        return (string) $this->cache->get($key);
    }

    private function newVersion(): string
    {
        return (string) Str::uuid7();
    }

    private function versionKey(string $organizationId): string
    {
        return "tax:configuration-version:{$organizationId}";
    }
}
