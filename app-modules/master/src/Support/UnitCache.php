<?php

declare(strict_types=1);

namespace Lahatre\Master\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

class UnitCache
{
    private const int TTL = 86400; // 24 hours

    /** @var Collection<string, Unit>|null Local request cache keyed by code */
    private ?Collection $units = null;

    /** @var Collection<string, Collection<string, Unit>>|null Local request cache grouped by group_id */
    private ?Collection $unitsByGroup = null;

    /** @var Collection<string, Unit>|null Local request cache of base units keyed by group_id */
    private ?Collection $baseUnits = null;

    /** @var Collection<string, Currency>|null Local request cache keyed by code */
    private ?Collection $currencies = null;

    /**
     * Get all units from cache, keyed by code.
     *
     * @return Collection<string, Unit>
     */
    public function units(): Collection
    {
        if ($this->units !== null) {
            return $this->units;
        }

        $organizationId = currentOrganizationId();
        $key = $this->unitsCacheKey($organizationId);

        return $this->units = Cache::remember(
            $key,
            self::TTL,
            fn (): Collection => Unit::query()
                ->where(function ($query) use ($organizationId): void {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', $organizationId);
                })
                ->get()
                ->keyBy('code')
        );
    }

    /**
     * Get units grouped by group_id.
     *
     * @return Collection<string, Collection<string, Unit>>
     */
    private function unitsByGroup(): Collection
    {
        return $this->unitsByGroup ??= $this->units()->groupBy('group_id');
    }

    /**
     * Get base units keyed by group_id.
     *
     * @return Collection<string, Unit>
     */
    private function baseUnits(): Collection
    {
        return $this->baseUnits ??= $this->units()->where('ratio', 1)->keyBy('group_id');
    }

    /**
     * Get all currencies from cache, keyed by code.
     *
     * @return Collection<string, Currency>
     */
    public function currencies(): Collection
    {
        if ($this->currencies !== null) {
            return $this->currencies;
        }

        // Currency doesn't have organization_id yet, but keeping consistency if it gets added
        $key = 'master:currencies:all';

        return $this->currencies = Cache::remember($key, self::TTL, fn () => Currency::all()->keyBy('code'));
    }

    /**
     * Get multiple units by code from the cached collection.
     *
     * @param  Collection<int, string>  $codes
     * @return Collection<string, Unit>
     */
    public function getByCodes(Collection $codes): Collection
    {
        if ($codes->isEmpty()) {
            return collect();
        }

        return $this->units()->whereIn('code', $codes->all())->keyBy('code');
    }

    /**
     * Get multiple currencies by code from the cached collection.
     *
     * @param  Collection<int, string>  $codes
     * @return Collection<string, Currency>
     */
    public function getCurrenciesByCodes(Collection $codes): Collection
    {
        if ($codes->isEmpty()) {
            return collect();
        }

        return $this->currencies()->whereIn('code', $codes->all())->keyBy('code');
    }

    /**
     * Get a unit by its code.
     */
    public function getByCode(string $code): Unit
    {
        $unit = $this->units()->get($code);

        if (!$unit) {
            throw new ModelNotFoundException()->setModel(Unit::class, [$code]);
        }

        return $unit;
    }

    /**
     * Get a currency by its code.
     */
    public function getCurrencyByCode(string $code): Currency
    {
        $currency = $this->currencies()->get($code);

        if (!$currency) {
            throw new ModelNotFoundException()->setModel(Currency::class, [$code]);
        }

        return $currency;
    }

    /**
     * Get all units of a group.
     *
     * @return Collection<string, Unit>
     */
    public function getByGroupId(string $groupId): Collection
    {
        return $this->unitsByGroup()->get($groupId, collect());
    }

    /**
     * Get the base unit of a group (ratio = 1).
     */
    public function getBaseUnit(string $groupId): Unit
    {
        $unit = $this->baseUnits()->get($groupId);

        if (!$unit) {
            throw new ModelNotFoundException()->setModel(Unit::class, ["base for group {$groupId}"]);
        }

        return $unit;
    }

    /**
     * Invalidate and rewarm the units cache.
     */
    public function rewarmUnits(): void
    {
        $organizationId = currentOrganizationId();
        $key = $this->unitsCacheKey($organizationId);

        $this->units = null;
        $this->unitsByGroup = null;
        $this->baseUnits = null;
        Cache::forget($key);
        $this->units();
    }

    /**
     * Invalidate and rewarm the currencies cache.
     */
    public function rewarmCurrencies(): void
    {
        $this->currencies = null;
        Cache::forget('master:currencies:all');
        $this->currencies();
    }

    private function unitsCacheKey(string $organizationId): string
    {
        return 'master:units:all:'.$organizationId;
    }
}
