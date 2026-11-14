<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Closure;
use Illuminate\Support\Collection;
use Lahatre\Shared\Support\GenerationalCache\GenerationalCache;

/**
 * Compiled profiles of an organization, cached as one generational group:
 * every write to the organization's taxes or profiles invalidates the whole
 * group after commit, since one tax can change many profiles. The database
 * stays authoritative; decisions read it, not this cache.
 */
final readonly class CompiledTaxProfileCache
{
    private const int TTL_SECONDS = 86_400;

    public function __construct(
        private GenerationalCache $cache,
    ) {}

    /**
     * @param  Collection<int, string>  $profileIds
     * @param  Closure(Collection<int, string>): Collection<string, CompiledTaxProfile>  $compile  compiles the ids missing from the cache
     * @return Collection<string, CompiledTaxProfile> keyed by profile id; unknown ids are absent
     */
    public function many(string $organizationId, Collection $profileIds, Closure $compile): Collection
    {
        /** @var Collection<string, CompiledTaxProfile> */
        return $this->cache->many(
            $this->group($organizationId),
            $profileIds,
            self::TTL_SECONDS,
            $compile,
            fn (CompiledTaxProfile $profile): array => $profile->toArray(),
            fn (mixed $payload): CompiledTaxProfile => CompiledTaxProfile::fromArray($payload),
        );
    }

    public function invalidateAfterCommit(string $organizationId): void
    {
        $this->cache->invalidateAfterCommit($this->group($organizationId));
    }

    private function group(string $organizationId): string
    {
        return "tax:compiled-profile:{$organizationId}";
    }
}
