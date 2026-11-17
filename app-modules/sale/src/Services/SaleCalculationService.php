<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Data\SellableItemQueryData;
use Lahatre\Catalog\ViewData\SellableItemViewData;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Sale\Assertions\SaleAssertion;
use Lahatre\Sale\Data\SaleData;
use Lahatre\Sale\Data\SaleLineData;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Shared\Support\Decimal;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\ViewData\TaxResultViewData;

/**
 * Assumes the caller's transaction. Reads are done once for all lines.
 *
 * @phpstan-type DraftLine array{position: int, catalog_item_id: string, quantity: int, item_type: string, item_name: string, item_sku: string, display_unit_code: string, unit_name: string, price_level_id: string, price_level_name: string, unit_amount: int, tax_profile_id: string|null, amount: int, base_amount: int, included_tax_amount: int, added_tax_amount: int, total_amount: int, tax_result: string}
 * @phpstan-type Draft array{header: array<string, mixed>, lines: list<DraftLine>}
 * @phpstan-type PriceChange array{item: string, price_level_id: string, price_level_name: string, approved_unit_amount: string, current_price_level_id: string, current_price_level_name: string, current_unit_amount: string}
 */
final readonly class SaleCalculationService
{
    /** The largest amount a line may reach: 18 digits. */
    private const int MAX_AMOUNT = 999_999_999_999_999_999;

    public function __construct(
        private SaleAssertion $saleAssertion,
        private SaleSettingService $saleSettingService,
        private CatalogInterface $catalogInterface,
        private CustomerInterface $customerInterface,
        private OrganizationInterface $organizationInterface,
        private TaxInterface $taxInterface,
        private MasterInterface $masterInterface,
    ) {}

    /**
     * The system never picks a price: it checks the one sent.
     *
     * @throws SaleException If the customer, a credit to the walk-in customer, a line, its level or its amount is refused.
     * @throws TaxException If the taxes of a line cannot be calculated.
     *
     * @return Draft
     */
    public function draft(SaleData $data, string $organizationId): array
    {
        $customer = $this->customer($data->customerId, $organizationId);
        $this->saleAssertion->assertCustomerCanBuy($customer);
        $requiredPaymentPercent = $data->requiredPaymentPercent ?? Sale::FULL_PAYMENT_PERCENT;
        $this->saleAssertion->assertCreditAllowed($customer, $requiredPaymentPercent);

        $functionalCurrency = $this->organizationInterface->functionalCurrency($organizationId);
        $sellableItems = $this->sellableItems($data->lines, $customer, $functionalCurrency, isDraftWrite: true);
        $lines = $this->lines($data->lines, $sellableItems, $functionalCurrency);

        return [
            'header' => [
                'mode'                     => $data->mode ?? $this->saleSettingService->defaultMode($organizationId),
                'required_payment_percent' => $requiredPaymentPercent,
                'payment_due_date'         => $data->paymentDueDate?->toDateString(),
                'approval_reasons'         => null,
                ...$this->copies($customer, $functionalCurrency),
                ...$this->totals($lines),
            ],
            'lines' => $lines,
        ];
    }

    /**
     * Calculates the lines again at their approved prices. The catalog prices that moved since,
     * at the line's level or at a level the customer was since tied to, are listed, not refused:
     * the caller decides. The amounts kept are not checked here either.
     *
     * @param  Collection<int, SaleLine>  $storedLines  ordered by position
     *
     * @throws SaleException If the customer may no longer buy, or a line or its level is no longer sellable.
     * @throws TaxException If the taxes of a line cannot be calculated.
     *
     * @return array{header: array<string, mixed>, lines: list<DraftLine>, price_changes: list<PriceChange>}
     */
    public function recalculate(Sale $sale, Collection $storedLines, string $organizationId): array
    {
        $customer = $this->customer($sale->customer_id, $organizationId);
        $this->saleAssertion->assertCustomerCanBuy($customer);
        $functionalCurrency = $this->organizationInterface->functionalCurrency($organizationId);

        $approvedLines = $storedLines->values()->map(fn (SaleLine $line): SaleLineData => SaleLineData::fromArray([
            'catalog_item_id' => $line->catalog_item_id,
            'unit_code'       => $line->display_unit_code,
            'quantity'        => (int) $this->masterInterface->convertUnitFromBase((string) $line->quantity, $line->display_unit_code)['amount'],
            'price_level_id'  => $line->price_level_id,
            'unit_amount'     => $this->masterInterface->fromMinor((string) $line->unit_amount, $sale->currency_code),
        ]));
        $sellableItems = $this->sellableItems($approvedLines, $customer, $functionalCurrency, isDraftWrite: false);
        $lines = $this->lines($approvedLines, $sellableItems, $functionalCurrency);

        return [
            'header' => [
                ...$this->copies($customer, $functionalCurrency),
                ...$this->totals($lines),
            ],
            'lines'         => $lines,
            'price_changes' => $this->priceChanges($approvedLines, $sellableItems, $customer, $sale->currency_code),
        ];
    }

    /**
     * @param  list<DraftLine>  $lines
     */
    public function insertLines(Sale $sale, array $lines): void
    {
        $now = now();

        SaleLine::query()->insert(array_map(fn (array $line): array => [
            ...$line,
            'id'              => (string) Str::uuid7(),
            'organization_id' => $sale->organization_id,
            'sale_id'         => $sale->id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ], $lines));
    }

    /** @throws SaleException */
    private function customer(?string $customerId, string $organizationId): Customer
    {
        if ($customerId === null) {
            return $this->customerInterface->walkInCustomer($organizationId);
        }

        return Customer::query()
            ->where('organization_id', $organizationId)
            ->whereKey($customerId)
            ->first()
            ?? throw SaleException::customerUnavailable($customerId);
    }

    /** @return array{customer_id: string, customer_name: string, currency_code: string} */
    private function copies(Customer $customer, Currency $functionalCurrency): array
    {
        return [
            'customer_id'   => $customer->id,
            'customer_name' => $customer->name,
            'currency_code' => $functionalCurrency->code,
        ];
    }

    /**
     * Reads all lines from the catalog at once, so the query count does not grow with the lines.
     * A draft write also checks each amount sent is the price held now, and that the customer's
     * own level is used; a recalculation leaves both to the caller.
     *
     * @param  Collection<int, SaleLineData>  $lines
     *
     * @throws SaleException
     *
     * @return Collection<string, SellableItemViewData>
     */
    private function sellableItems(Collection $lines, Customer $customer, Currency $functionalCurrency, bool $isDraftWrite): Collection
    {
        $lines = $lines->values();
        $request = fn (SaleLineData $line, string $priceLevelId): SellableItemQueryData => SellableItemQueryData::fromArray([
            'catalog_item_id' => $line->catalogItemId,
            'unit_code'       => $line->unitCode,
            'price_level_id'  => $priceLevelId,
        ]);
        $requests = $lines->map(fn (SaleLineData $line): SellableItemQueryData => $request($line, $line->priceLevelId));

        if ($customer->price_level_id !== null) {
            $requests = $requests->merge($lines->map(fn (SaleLineData $line): SellableItemQueryData => $request($line, (string) $customer->price_level_id)));
        }

        $sellableItems = $this->catalogInterface->sellableItems($customer->organization_id, $requests);
        $this->saleAssertion->assertLinesSellable(
            $lines,
            $sellableItems,
            $customer,
            $functionalCurrency,
            $isDraftWrite ? fn (SaleLineData $line, SellableItemViewData $item): bool => $this->isCurrentAmount($line, $item) : null,
            imposeCustomerLevel: $isDraftWrite,
        );

        return $sellableItems;
    }

    /**
     * Prices each line at the amount it carries: the one checked on a draft write, the approved one on a recalculation.
     *
     * @param  Collection<int, SaleLineData>  $lines
     * @param  Collection<string, SellableItemViewData>  $sellableItems
     *
     * @throws SaleException
     * @throws TaxException
     *
     * @return list<DraftLine>
     */
    private function lines(Collection $lines, Collection $sellableItems, Currency $functionalCurrency): array
    {
        $lines = $lines->values();
        $items = $lines->map(fn (SaleLineData $line): SellableItemViewData => $sellableItems->get(SellableItemViewData::key($line->catalogItemId, $line->unitCode, $line->priceLevelId)));
        $unitAmounts = $lines->map(fn (SaleLineData $line): int => (int) $this->masterInterface->toMinor($line->unitAmount, $functionalCurrency->code));
        $amounts = $lines->map(fn (SaleLineData $line, int $index): int => $this->lineAmount($unitAmounts->get($index), $line->quantity, $items->get($index)));
        $taxResults = $this->taxInterface->resolveMany($lines->map(fn (SaleLineData $line, int $index): TaxableLineData => TaxableLineData::fromArray([
            'tax_profile_id'   => $items->get($index)->taxProfileId,
            'amount'           => (string) $amounts->get($index),
            'included_tax_ids' => $items->get($index)->includedTaxIds,
            'currency_code'    => $functionalCurrency->code,
            'quantity'         => (string) $line->quantity,
            'unit_code'        => $line->unitCode,
        ])));

        return $lines->map(function (SaleLineData $line, int $index) use ($items, $unitAmounts, $taxResults): array {
            /** @var SellableItemViewData $item */
            $item = $items->get($index);
            /** @var TaxResultViewData $taxResult */
            $taxResult = $taxResults->get($index);
            $label = SaleAssertion::label($item);

            return [
                'position'            => $index + 1,
                'catalog_item_id'     => $item->catalogItemId,
                'quantity'            => (int) $this->masterInterface->convertUnitToBase((string) $line->quantity, $item->unitCode)['amount'],
                'item_type'           => $item->itemType->value,
                'item_name'           => $item->name,
                'item_sku'            => $item->sku,
                'display_unit_code'   => $item->unitCode,
                'unit_name'           => (string) $item->unitName,
                'price_level_id'      => $item->priceLevelId,
                'price_level_name'    => (string) $item->priceLevelName,
                'unit_amount'         => $unitAmounts->get($index),
                'tax_profile_id'      => $item->taxProfileId,
                'amount'              => $this->minor($taxResult->amount, $label),
                'base_amount'         => $this->minor($taxResult->baseAmount, $label),
                'included_tax_amount' => $this->minor($taxResult->includedTaxAmount, $label),
                'added_tax_amount'    => $this->minor($taxResult->addedTaxAmount, $label),
                'total_amount'        => $this->minor($taxResult->totalAmount, $label),
                'tax_result'          => (string) json_encode($taxResult->toArray(), JSON_THROW_ON_ERROR),
            ];
        })->all();
    }

    /**
     * The lines whose catalog price moved away from the approved one: at the line's level, or at the
     * level the customer is now tied to when it prices the item.
     *
     * @param  Collection<int, SaleLineData>  $lines
     * @param  Collection<string, SellableItemViewData>  $sellableItems
     * @return list<PriceChange>
     */
    private function priceChanges(Collection $lines, Collection $sellableItems, Customer $customer, string $currencyCode): array
    {
        $changes = [];

        foreach ($lines->values() as $line) {
            /** @var SellableItemViewData $item */
            $item = $sellableItems->get(SellableItemViewData::key($line->catalogItemId, $line->unitCode, $line->priceLevelId));
            $imposed = $customer->price_level_id === null || $customer->price_level_id === $line->priceLevelId
                ? null
                : $sellableItems->get(SellableItemViewData::key($line->catalogItemId, $line->unitCode, (string) $customer->price_level_id));
            $current = $imposed?->unitAmount !== null ? $imposed : $item;
            $approvedAmount = $this->masterInterface->toMinor($line->unitAmount, $currencyCode);

            if ($current === $item && (string) $item->unitAmount === $approvedAmount) {
                continue;
            }

            $changes[] = [
                'item'                     => SaleAssertion::label($item),
                'price_level_id'           => $item->priceLevelId,
                'price_level_name'         => (string) $item->priceLevelName,
                'approved_unit_amount'     => $line->unitAmount,
                'current_price_level_id'   => $current->priceLevelId,
                'current_price_level_name' => (string) $current->priceLevelName,
                'current_unit_amount'      => $this->masterInterface->fromMinor((string) $current->unitAmount, $currencyCode),
            ];
        }

        return $changes;
    }

    /**
     * Whether the unit price the seller sent is the one the catalog holds at
     * that level now. An amount with more decimals than the currency allows
     * is never current.
     */
    private function isCurrentAmount(SaleLineData $line, SellableItemViewData $item): bool
    {
        $currencyCode = (string) $item->currencyCode;

        if (Decimal::places($line->unitAmount) > $this->masterInterface->currency($currencyCode)->precision) {
            return false;
        }

        return (int) $this->masterInterface->toMinor($line->unitAmount, $currencyCode) === $item->unitAmount;
    }

    /** @throws SaleException If the unit price times the quantity exceeds 18 digits. */
    private function lineAmount(int $unitAmount, int $quantity, SellableItemViewData $item): int
    {
        if ($unitAmount > intdiv(self::MAX_AMOUNT, $quantity)) {
            throw SaleException::lineAmountTooLarge(SaleAssertion::label($item));
        }

        return $unitAmount * $quantity;
    }

    /** @throws SaleException If an amount of the calculation exceeds 18 digits. */
    private function minor(string $amount, string $label): int
    {
        if (strlen(ltrim($amount, '-')) > 18) {
            throw SaleException::lineAmountTooLarge($label);
        }

        return (int) $amount;
    }

    /**
     * @param  list<DraftLine>  $lines
     *
     * @throws SaleException If a total does not fit.
     *
     * @return array{lines_base_total: int, lines_tax_total: int, lines_total: int, total: int}
     */
    private function totals(array $lines): array
    {
        $base = 0;
        $tax = 0;
        $total = 0;

        foreach ($lines as $line) {
            $base = $this->add($base, $line['base_amount']);
            $tax = $this->add($this->add($tax, $line['included_tax_amount']), $line['added_tax_amount']);
            $total = $this->add($total, $line['total_amount']);
        }

        return [
            'lines_base_total' => $base,
            'lines_tax_total'  => $tax,
            'lines_total'      => $total,
            'total'            => $total,
        ];
    }

    /** @throws SaleException */
    private function add(int $sum, int $amount): int
    {
        if ($sum > PHP_INT_MAX - $amount) {
            throw SaleException::totalTooLarge();
        }

        return $sum + $amount;
    }
}
