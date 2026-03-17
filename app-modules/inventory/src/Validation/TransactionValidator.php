<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Validation;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;

class TransactionValidator
{
    /**
     * Define the basic and complex validation rules.
     */
    public static function rules(): array
    {
        return [
            'reference_type'   => ['required', 'string'],
            'reference_id'     => ['required', 'string'],
            'transaction_type' => ['required', Rule::enum(TransactionType::class)],
            'metadata'         => ['nullable', 'array'],

            'movements' => [
                'required',
                'array',
                'min:1',
                // Rule 1: Unique (item_id + location_id)
                function (string $attribute, mixed $value, $fail): void {
                    $pairs = collect($value)->map(fn ($m): string => ($m['item_id'] ?? '').':'.($m['location_id'] ?? ''));
                    if ($pairs->count() !== $pairs->unique()->count()) {
                        $fail('The same item cannot appear multiple times for the same location in a single transaction.');
                    }
                },
                // Rule 2: Single currency across all 'in' movements
                function (string $attribute, mixed $value, $fail): void {
                    $currencies = collect($value)
                        ->filter(fn ($m): bool => ($m['type'] ?? null) === MovementType::In->value)
                        ->pluck('currency_code')
                        ->filter()
                        ->unique();
                    if ($currencies->count() > 1) {
                        $fail('All "in" movements in a transaction must use the same currency code.');
                    }
                },
                // Rule 3: Bulk Items validation
                function (string $attribute, mixed $value, $fail): void {
                    $itemIds = collect($value)->pluck('item_id')->filter()->unique();
                    $count = InventoryItem::whereIn('id', $itemIds)->where('is_active', true)->count();
                    if ($count !== $itemIds->count()) {
                        $fail('One or more selected items are invalid or inactive.');
                    }
                },
                // Rule 4: Bulk Locations validation
                function (string $attribute, mixed $value, $fail): void {
                    $locationIds = collect($value)->pluck('location_id')->filter()->unique();
                    $count = InventoryLocation::whereIn('id', $locationIds)->where('is_active', true)->count();
                    if ($count !== $locationIds->count()) {
                        $fail('One or more selected locations are invalid or inactive.');
                    }
                },
                // Rule 5: Bulk Units validation
                function (string $attribute, mixed $value, $fail): void {
                    $unitCodes = collect($value)->pluck('unit_code')->filter()->unique();
                    $count = DB::table('master_units')->whereIn('code', $unitCodes)->count();
                    if ($count !== $unitCodes->count()) {
                        $fail('One or more unit codes are invalid.');
                    }
                },
                // Rule 6: Bulk Currencies validation (only 'in' movements carry currency)
                function (string $attribute, mixed $value, $fail): void {
                    $currencyCodes = collect($value)
                        ->filter(fn ($m): bool => ($m['type'] ?? null) === MovementType::In->value)
                        ->pluck('currency_code')
                        ->filter()
                        ->unique();
                    $count = DB::table('master_currencies')->whereIn('code', $currencyCodes)->count();
                    if ($count !== $currencyCodes->count()) {
                        $fail('One or more currency codes are invalid.');
                    }
                },
                // Rule 7: unit_cost and currency_code required for 'in' movements
                function (string $attribute, mixed $value, $fail): void {
                    foreach ($value as $index => $movement) {
                        if (($movement['type'] ?? null) !== MovementType::In->value) {
                            continue;
                        }
                        if (!isset($movement['unit_cost'])) {
                            $fail("movements.{$index}.unit_cost is required for 'in' movements.");
                        }
                        if (empty($movement['currency_code'])) {
                            $fail("movements.{$index}.currency_code is required for 'in' movements.");
                        }
                    }
                },
                // Rule 8: Bulk Stock IDs validation
                function (string $attribute, mixed $value, $fail): void {
                    $allStockIds = collect($value)->pluck('stock_ids')->flatten()->filter()->unique();

                    if ($allStockIds->isEmpty()) {
                        return;
                    }

                    $stocks = InventoryStock::whereIn('id', $allStockIds)->get(['id', 'item_id', 'location_id']);

                    if ($stocks->count() !== $allStockIds->count()) {
                        $fail('One or more stock IDs are invalid.');
                    }

                    foreach ($value as $index => $m) {
                        if (empty($m['stock_ids'])) {
                            continue;
                        }

                        foreach ($m['stock_ids'] as $sid) {
                            $stock = $stocks->firstWhere('id', $sid);

                            if ($stock->item_id !== ($m['item_id'] ?? null) || $stock->location_id !== ($m['location_id'] ?? null)) {
                                $fail("Stock ID {$sid} does not belong to the correct item and location for movement at index {$index}.");
                            }
                        }
                    }
                },
                // Rule 9: Required stock_ids for manual strategy
                function (string $attribute, mixed $value, $fail): void {
                    foreach ($value as $index => $m) {
                        $strategy = $m['strategy'] ?? null;
                        if (is_string($strategy)) {
                            $strategy = DeductionStrategy::tryFrom($strategy);
                        }

                        if ($strategy === DeductionStrategy::Manual && (empty($m['stock_ids']))) {
                            $fail("movements.{$index}.stock_ids is required when strategy is manual.");
                        }
                    }
                },
            ],

            'movements.*.item_id'         => ['required', 'string'],
            'movements.*.location_id'     => ['required', 'string'],
            'movements.*.type'            => ['required', Rule::enum(MovementType::class)],
            'movements.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'movements.*.unit_code'       => ['required', 'string'],
            'movements.*.unit_cost'       => ['nullable', 'integer', 'min:0'],
            'movements.*.currency_code'   => ['nullable', 'string'],
            'movements.*.peremption_date' => ['nullable', 'date'],
            'movements.*.strategy'        => ['nullable', Rule::enum(DeductionStrategy::class)],
            'movements.*.stock_ids'       => ['nullable', 'array'],
            'movements.*.metadata'        => ['nullable', 'array'],
        ];
    }
}
