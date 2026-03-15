<?php

declare(strict_types=1);

namespace Lahatre\Master\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

class UnitCache
{
    private const TTL = 86400; // 24 hours

    /** @var Collection<int, Unit>|null Local request cache */
    private ?Collection $units = null;

    /** @var Collection<int, Currency>|null Local request cache */
    private ?Collection $currencies = null;

    /**
     * Get all units from cache.
     *
     * @return Collection<int, Unit>
     */
    public function units(): Collection
    {
        if ($this->units !== null) {
            return $this->units;
        }

        return $this->units = Cache::remember('master:units:all', self::TTL, fn () => Unit::all());
    }

    /**
     * Get all currencies from cache.
     *
     * @return Collection<int, Currency>
     */
    public function currencies(): Collection
    {
        if ($this->currencies !== null) {
            return $this->currencies;
        }

        return $this->currencies = Cache::remember('master:currencies:all', self::TTL, fn () => Currency::all());
    }

    /**
     * Get a unit by its code.
     */
    public function getByCode(string $code): Unit
    {
        $unit = $this->units()->firstWhere('code', $code);

        if (!$unit) {
            throw (new ModelNotFoundException())->setModel(Unit::class, [$code]);
        }

        return $unit;
    }

    /**
     * Get a currency by its code.
     */
    public function getCurrencyByCode(string $code): Currency
    {
        $currency = $this->currencies()->firstWhere('code', $code);

        if (!$currency) {
            throw (new ModelNotFoundException())->setModel(Currency::class, [$code]);
        }

        return $currency;
    }

    /**
     * Get all units of a group.
     *
     * @return Collection<int, Unit>
     */
    public function getByGroupId(string $groupId): Collection
    {
        return $this->units()->where('group_id', $groupId);
    }

    /**
     * Get the base unit of a group (ratio = 1).
     */
    public function getBaseUnit(string $groupId): Unit
    {
        $unit = $this->getByGroupId($groupId)->firstWhere('ratio', 1);

        if (!$unit) {
            throw (new ModelNotFoundException())->setModel(Unit::class, ["base for group {$groupId}"]);
        }

        return $unit;
    }

    /**
     * Invalidate and rewarm the units cache.
     */
    public function rewarmUnits(): void
    {
        $this->units = null;
        Cache::forget('master:units:all');
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
}
