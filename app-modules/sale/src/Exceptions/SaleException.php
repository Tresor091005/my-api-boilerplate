<?php

declare(strict_types=1);

namespace Lahatre\Sale\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

/**
 * A sale refused. The messages name what blocks, since the context is hidden in production.
 */
final class SaleException extends AssertionException
{
    private const int LISTED_ITEMS = 3;

    public static function customerUnavailable(string $customerId): self
    {
        return new self(
            __('sale::exceptions.customer_unavailable'),
            ['customer_id' => $customerId],
        );
    }

    public static function customerInactive(string $customerId, string $customerName): self
    {
        return new self(
            __('sale::exceptions.customer_inactive', ['customer' => $customerName]),
            ['customer_id' => $customerId],
        );
    }

    /** @param list<string> $labels "SKU (unit)" of each line sold at another level */
    public static function priceLevelImposed(string $customerName, string $customerPriceLevelId, array $labels): self
    {
        return new self(
            __('sale::exceptions.price_level_imposed', ['customer' => $customerName, 'items' => self::listed($labels)]),
            ['customer_price_level_id' => $customerPriceLevelId, 'items' => $labels],
        );
    }

    /** @param list<string> $labels */
    public static function priceLevelsUnavailable(array $labels): self
    {
        return new self(
            __('sale::exceptions.price_levels_unavailable', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    public static function priceLevelOutsideFunctionalCurrency(string $priceLevelName, string $currencyCode, string $functionalCurrencyCode): self
    {
        return new self(
            __('sale::exceptions.price_level_outside_functional_currency', [
                'level'      => $priceLevelName,
                'currency'   => $currencyCode,
                'functional' => $functionalCurrencyCode,
            ]),
            ['currency_code' => $currencyCode, 'functional_currency_code' => $functionalCurrencyCode],
        );
    }

    /** @param list<int> $positions line numbers, from 1 */
    public static function itemsUnavailable(array $positions): self
    {
        return new self(
            __('sale::exceptions.items_unavailable', ['lines' => self::listed(array_map(strval(...), $positions))]),
            ['positions' => $positions],
        );
    }

    /** @param list<string> $labels "SKU (unit)" of each line */
    public static function itemsInactive(array $labels): self
    {
        return new self(
            __('sale::exceptions.items_inactive', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    /** @param list<string> $labels */
    public static function servicesNotSold(array $labels): self
    {
        return new self(
            __('sale::exceptions.services_not_sold', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    /** @param list<string> $labels */
    public static function unitsOutsideItemGroup(array $labels): self
    {
        return new self(
            __('sale::exceptions.units_outside_item_group', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    /** @param list<string> $labels "SKU (unit) at level" of each line */
    public static function pricesMissing(array $labels): self
    {
        return new self(
            __('sale::exceptions.prices_missing', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    /** @param list<string> $labels "SKU (unit) at level" of each line */
    public static function pricesChanged(array $labels): self
    {
        return new self(
            __('sale::exceptions.prices_changed', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    /** @param list<string> $labels */
    public static function linesDuplicated(array $labels): self
    {
        return new self(
            __('sale::exceptions.lines_duplicated', ['items' => self::listed($labels)]),
            ['items' => $labels],
        );
    }

    public static function lineAmountTooLarge(string $label): self
    {
        return new self(
            __('sale::exceptions.line_amount_too_large', ['item' => $label]),
            ['item' => $label],
        );
    }

    public static function totalTooLarge(): self
    {
        return new self(__('sale::exceptions.total_too_large'));
    }

    public static function notDraft(string $number, string $status): self
    {
        return new self(
            __('sale::exceptions.not_draft', ['number' => $number, 'status' => $status]),
            ['number' => $number, 'status' => $status],
        );
    }

    public static function versionChanged(string $number, int $currentVersion, int $sentVersion): self
    {
        return new self(
            __('sale::exceptions.version_changed', ['number' => $number]),
            ['number' => $number, 'current_version' => $currentVersion, 'sent_version' => $sentVersion],
        );
    }

    /** @param list<string> $allowedEvents the gestures the status allows */
    public static function gestureNotAllowed(string $number, string $status, string $event, array $allowedEvents): self
    {
        $key = $allowedEvents === [] ? 'sale::exceptions.gesture_not_allowed_final' : 'sale::exceptions.gesture_not_allowed';

        return new self(
            __($key, ['number' => $number, 'status' => $status, 'event' => $event, 'events' => implode(', ', $allowedEvents)]),
            ['number' => $number, 'status' => $status, 'event' => $event, 'allowed_events' => $allowedEvents],
        );
    }

    public static function creditToWalkInCustomer(string $customerId, int $requiredPaymentPercent): self
    {
        return new self(
            __('sale::exceptions.credit_to_walk_in_customer'),
            ['customer_id' => $customerId, 'required_payment_percent' => $requiredPaymentPercent],
        );
    }

    public static function paymentDueDateMissing(string $number, int $requiredPaymentPercent): self
    {
        return new self(
            __('sale::exceptions.payment_due_date_missing', ['number' => $number]),
            ['number' => $number, 'required_payment_percent' => $requiredPaymentPercent],
        );
    }

    /** @param string $dueDate Y-m-d */
    public static function paymentDueDatePassed(string $number, string $dueDate): self
    {
        return new self(
            __('sale::exceptions.payment_due_date_passed', ['number' => $number, 'date' => $dueDate]),
            ['number' => $number, 'payment_due_date' => $dueDate],
        );
    }

    /** @param list<string> $labels "SKU (unit)" of each line whose amounts changed */
    public static function amountsChanged(string $number, array $labels): self
    {
        return new self(
            __('sale::exceptions.amounts_changed', [
                'number' => $number,
                'items'  => self::listed($labels),
            ]),
            ['number' => $number, 'items' => $labels],
        );
    }

    /** @param list<string> $labels */
    private static function listed(array $labels): string
    {
        $shown = implode(', ', array_slice($labels, 0, self::LISTED_ITEMS));
        $hidden = count($labels) - self::LISTED_ITEMS;

        return $hidden > 0 ? __('sale::exceptions.and_more', ['list' => $shown, 'count' => $hidden]) : $shown;
    }

    /**
     * @param  list<array{item: string, price_level_name: string, approved_unit_amount: string, current_price_level_name: string, current_unit_amount: string}>  $changes
     */
    public static function pricesChangedSinceApproval(string $number, array $changes): self
    {
        $labels = array_map(fn (array $change): string => __(
            $change['current_price_level_name'] === $change['price_level_name'] ? 'sale::exceptions.price_change' : 'sale::exceptions.price_change_with_level',
            [
                'line'     => __('sale::exceptions.priced_line', ['line' => $change['item'], 'level' => $change['price_level_name']]),
                'approved' => $change['approved_unit_amount'],
                'current'  => $change['current_unit_amount'],
                'level'    => $change['current_price_level_name'],
            ],
        ), $changes);

        return new self(
            __('sale::exceptions.prices_changed_since_approval', ['number' => $number, 'items' => self::listed($labels)]),
            ['number' => $number, 'price_changes' => $changes],
        );
    }

    /** @param list<string> $shortages each line, location and quantities lacking */
    public static function stockInsufficient(array $shortages): self
    {
        return new self(
            __('sale::exceptions.stock_insufficient', ['items' => self::listed($shortages)]),
            ['shortages' => $shortages],
        );
    }

    public static function saleNotFulfillable(string $number, string $status): self
    {
        return new self(
            __('sale::exceptions.sale_not_fulfillable', ['number' => $number, 'status' => $status]),
            ['number' => $number, 'status' => $status],
        );
    }

    public static function fulfillmentNotDraft(string $number, string $status): self
    {
        return new self(
            __('sale::exceptions.fulfillment_not_draft', ['number' => $number, 'status' => $status]),
            ['number' => $number, 'status' => $status],
        );
    }

    public static function fulfillmentNotCompleted(string $number, string $status): self
    {
        return new self(
            __('sale::exceptions.fulfillment_not_completed', ['number' => $number, 'status' => $status]),
            ['number' => $number, 'status' => $status],
        );
    }

    /** Wraps a refusal met while confirming a locked sale. */
    public static function confirmationBlocked(string $number, self $obstacle): self
    {
        return new self(
            __('sale::exceptions.confirmation_blocked', ['number' => $number, 'reason' => $obstacle->getMessage()]),
            ['number' => $number, ...$obstacle->context()],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
