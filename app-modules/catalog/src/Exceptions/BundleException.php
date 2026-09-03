<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Bundle;
use Lahatre\Shared\Exceptions\AssertionException;

final class BundleException extends AssertionException
{
    public static function systemUnitGroupMissing(): self
    {
        return new self(__('catalog::exceptions.bundle_system_unit_group_missing'));
    }

    /** @param list<string> $itemIds */
    public static function itemsUnavailable(array $itemIds): self
    {
        return new self(
            __('catalog::exceptions.bundle_items_unavailable'),
            ['item_ids' => $itemIds],
        );
    }

    public static function duplicateItem(string $itemId): self
    {
        return new self(
            __('catalog::exceptions.bundle_duplicate_item'),
            ['item_id' => $itemId],
        );
    }

    public static function itemTypeNotAllowed(string $itemId, string $itemType): self
    {
        return new self(
            __('catalog::exceptions.bundle_item_type_not_allowed'),
            ['item_id' => $itemId, 'item_type' => $itemType],
        );
    }

    public static function itemUnitMismatch(string $itemId, string $unitCode): self
    {
        return new self(
            __('catalog::exceptions.bundle_item_unit_mismatch'),
            ['item_id' => $itemId, 'unit_code' => $unitCode],
        );
    }

    public static function requiresTwoItems(?Bundle $bundle = null): self
    {
        return new self(
            __('catalog::exceptions.bundle_requires_two_items'),
            $bundle instanceof Bundle ? ['bundle_id' => $bundle->id] : [],
        );
    }

    public static function quantityMustBePositive(): self
    {
        return new self(__('catalog::exceptions.bundle_quantity_must_be_positive'));
    }

    /** @param list<array{code: string, context: array<string, string>}> $errors */
    public static function stockOperationInvalidState(array $errors): self
    {
        return new self(
            __('catalog::exceptions.bundle_stock_operation_invalid_state'),
            ['errors' => $errors],
        );
    }

    public static function stockOperationCompositionChanged(string $operationId): self
    {
        return new self(
            __('catalog::exceptions.bundle_stock_operation_composition_changed'),
            ['operation_id' => $operationId],
        );
    }

    public static function stockOperationCurrencyMismatch(): self
    {
        return new self(__('catalog::exceptions.bundle_stock_operation_currency_mismatch'));
    }

    public static function stockOperationCostAllocationMismatch(): self
    {
        return new self(__('catalog::exceptions.bundle_stock_operation_cost_allocation_mismatch'));
    }

    public static function cannotChangeCompositionWithActiveStock(Bundle $bundle): self
    {
        return new self(
            __('catalog::exceptions.bundle_cannot_change_composition_with_active_stock'),
            ['bundle_id' => $bundle->id],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
