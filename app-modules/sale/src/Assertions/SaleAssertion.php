<?php

declare(strict_types=1);

namespace Lahatre\Sale\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\ViewData\SellableItemViewData;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Models\Currency;
use Lahatre\Sale\Data\SaleLineData;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;

final readonly class SaleAssertion
{
    /**
     * @throws SaleException If the customer is inactive.
     */
    public function assertCustomerCanBuy(Customer $customer): void
    {
        if (!$customer->is_active) {
            throw SaleException::customerInactive($customer->id, $customer->name);
        }
    }

    /**
     * A share below 100 % is a credit, never granted to the walk-in customer.
     *
     * @throws SaleException If a credit is asked for the walk-in customer.
     */
    public function assertCreditAllowed(Customer $customer, int $requiredPaymentPercent): void
    {
        if ($requiredPaymentPercent < Sale::FULL_PAYMENT_PERCENT && $customer->is_walk_in) {
            throw SaleException::creditToWalkInCustomer($customer->id, $requiredPaymentPercent);
        }
    }

    /**
     * Checks every line can be sold at the level it names, and the amounts sent when asked.
     *
     * @param  Collection<int, SaleLineData>  $lines
     * @param  Collection<string, SellableItemViewData>  $sellableItems  the catalog's answer for each line's level, and for the customer's own level when they have one
     * @param  callable(SaleLineData, SellableItemViewData): bool  $amountIsCurrent  whether the amount sent is the price held, or null not to check it
     * @param  bool  $imposeCustomerLevel  whether a line must use the customer's own level when it prices the item
     *
     * @throws SaleException On the first kind of refusal met, naming every line it concerns.
     */
    public function assertLinesSellable(
        Collection $lines,
        Collection $sellableItems,
        Customer $customer,
        Currency $functionalCurrency,
        ?callable $amountIsCurrent,
        bool $imposeCustomerLevel,
    ): void {
        $this->assertLinesUnique($lines, $sellableItems);

        $missingPositions = $lines
            ->filter(fn (SaleLineData $line): bool => !$sellableItems->has($this->key($line)))
            ->keys()
            ->map(fn (int $index): int => $index + 1)
            ->values()
            ->all();
        if ($missingPositions !== []) {
            throw SaleException::itemsUnavailable($missingPositions);
        }

        $items = $lines->map(fn (SaleLineData $line): SellableItemViewData => $sellableItems->get($this->key($line)));
        $refused = fn (callable $isRefused, callable $label): array => $items->filter($isRefused)
            ->map(fn (SellableItemViewData $item): string => $label($item))
            ->values()
            ->all();

        $inactive = $refused(fn (SellableItemViewData $item): bool => !$item->isActive, self::label(...));
        if ($inactive !== []) {
            throw SaleException::itemsInactive($inactive);
        }

        $services = $refused(fn (SellableItemViewData $item): bool => $item->itemType === CatalogItemType::Service, self::label(...));
        if ($services !== []) {
            throw SaleException::servicesNotSold($services);
        }

        $foreignUnits = $refused(fn (SellableItemViewData $item): bool => $item->unitId === null, self::label(...));
        if ($foreignUnits !== []) {
            throw SaleException::unitsOutsideItemGroup($foreignUnits);
        }

        $levelsGone = $refused(fn (SellableItemViewData $item): bool => $item->priceLevelName === null, self::label(...));
        if ($levelsGone !== []) {
            throw SaleException::priceLevelsUnavailable($levelsGone);
        }

        $customerLevelId = $customer->price_level_id;
        if ($imposeCustomerLevel && $customerLevelId !== null) {
            $imposed = $refused(fn (SellableItemViewData $item): bool => $item->priceLevelId !== $customerLevelId
                && $sellableItems->get(SellableItemViewData::key($item->catalogItemId, $item->unitCode, $customerLevelId))?->unitAmount !== null, self::label(...));
            if ($imposed !== []) {
                throw SaleException::priceLevelImposed($customer->name, $customerLevelId, $imposed);
            }
        }

        $foreignCurrency = $items->first(fn (SellableItemViewData $item): bool => $item->currencyCode !== $functionalCurrency->code);
        if ($foreignCurrency instanceof SellableItemViewData) {
            throw SaleException::priceLevelOutsideFunctionalCurrency((string) $foreignCurrency->priceLevelName, (string) $foreignCurrency->currencyCode, $functionalCurrency->code);
        }

        $unpriced = $refused(fn (SellableItemViewData $item): bool => $item->unitAmount === null, self::pricedLabel(...));
        if ($unpriced !== []) {
            throw SaleException::pricesMissing($unpriced);
        }

        if ($amountIsCurrent === null) {
            return;
        }

        $changed = $lines
            ->filter(fn (SaleLineData $line, int $index): bool => !$amountIsCurrent($line, $items->get($index)))
            ->map(fn (SaleLineData $line, int $index): string => self::pricedLabel($items->get($index)))
            ->values()
            ->all();
        if ($changed !== []) {
            throw SaleException::pricesChanged($changed);
        }
    }

    /**
     * @throws SaleException If the sale is not a draft, or changed since it was read.
     */
    public function assertDraftReplaceable(Sale $sale, int $version): void
    {
        if ($sale->status !== SaleStatus::Draft) {
            throw SaleException::notDraft($sale->number, $sale->status->value);
        }

        $this->assertVersionCurrent($sale, $version);
    }

    /**
     * @throws SaleException If the sale changed since it was read.
     */
    public function assertVersionCurrent(Sale $sale, int $version): void
    {
        if ($sale->version !== $version) {
            throw SaleException::versionChanged($sale->number, $sale->version, $version);
        }
    }

    /**
     * @param  Collection<int, SaleLine>  $storedLines  ordered by position
     * @param  list<array<string, mixed>>  $recalculatedLines  the same lines, in the same order, as a draft write gives them
     *
     * @throws SaleException Naming every item whose amounts changed.
     */
    public function assertAmountsUnchanged(Sale $sale, Collection $storedLines, array $recalculatedLines): void
    {
        $changed = $storedLines->values()
            ->filter(fn (SaleLine $line, int $index): bool => !$this->sameAmounts($line, $recalculatedLines[$index]))
            ->map(fn (SaleLine $line, int $index): string => "{$recalculatedLines[$index]['item_sku']} ({$recalculatedLines[$index]['display_unit_code']})")
            ->values()
            ->all();

        if ($changed !== []) {
            throw SaleException::amountsChanged($sale->number, $changed);
        }
    }

    public static function label(SellableItemViewData $item): string
    {
        return "{$item->sku} ({$item->unitCode})";
    }

    public static function pricedLabel(SellableItemViewData $item): string
    {
        return __('sale::exceptions.priced_line', ['line' => self::label($item), 'level' => (string) $item->priceLevelName]);
    }

    /**
     * @param  Collection<int, SaleLineData>  $lines
     * @param  Collection<string, SellableItemViewData>  $sellableItems
     *
     * @throws SaleException
     */
    private function assertLinesUnique(Collection $lines, Collection $sellableItems): void
    {
        $duplicates = $lines
            ->groupBy(fn (SaleLineData $line): string => "{$line->catalogItemId}:{$line->unitCode}")
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): string => $sellableItems->has($this->key($group->first()))
                ? self::label($sellableItems->get($this->key($group->first())))
                : "{$group->first()->catalogItemId} ({$group->first()->unitCode})")
            ->values()
            ->all();

        if ($duplicates !== []) {
            throw SaleException::linesDuplicated($duplicates);
        }
    }

    /** @param array<string, mixed> $recalculated */
    /**
     * Only what the customer pays counts: a renamed tax, or a bracket the line
     * does not reach, leaves the sale as it was approved.
     */
    private function sameAmounts(SaleLine $line, array $recalculated): bool
    {
        foreach (['unit_amount', 'amount', 'base_amount', 'included_tax_amount', 'added_tax_amount', 'total_amount'] as $column) {
            if ($line->getAttribute($column) !== $recalculated[$column]) {
                return false;
            }
        }

        return $this->taxAmounts($line->tax_result) === $this->taxAmounts(json_decode((string) $recalculated['tax_result'], true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $taxResult
     * @return array<string, string> the amount of each tax, keyed by tax id
     */
    private function taxAmounts(array $taxResult): array
    {
        $amounts = collect($taxResult['taxes'] ?? [])
            ->mapWithKeys(fn (array $tax): array => [(string) $tax['tax_id'] => (string) $tax['tax_amount']])
            ->all();
        ksort($amounts);

        return $amounts;
    }

    private function key(SaleLineData $line): string
    {
        return SellableItemViewData::key($line->catalogItemId, $line->unitCode, $line->priceLevelId);
    }
}
