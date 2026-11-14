<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Master\Contracts\MasterInterface;

/**
 * A price amount is a non-negative decimal with no more decimal places than
 * the currency of its price level has.
 */
trait ValidatesPriceAmount
{
    /** @return list<string> the format of an amount; each request adds its own presence rule */
    protected function amountRules(int $maxPlaces = 8): array
    {
        return ['decimal:0,'.$maxPlaces, 'min:0', 'max:1000000000000'];
    }

    /**
     * The decimal places each level's currency allows, keyed by level id.
     *
     * @param  Collection<int, string>  $priceLevelIds
     * @return Collection<string, int>
     */
    protected function precisionsByLevel(Collection $priceLevelIds): Collection
    {
        $currencyCodes = PriceLevel::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $priceLevelIds->filter(fn (mixed $id): bool => is_string($id) && Str::isUuid($id))->unique()->values())
            ->pluck('currency_code', 'id');
        $currencies = app(MasterInterface::class)->currencies($currencyCodes->unique()->values());

        return $currencyCodes->map(fn (string $code): int => (int) $currencies->get($code)?->precision);
    }
}
