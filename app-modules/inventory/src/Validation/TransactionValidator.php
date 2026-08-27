<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Validation;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\BaseUnitNotFoundException;
use Lahatre\Inventory\Exceptions\BaseUnitRatioIntegrityException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Unit;

class TransactionValidator
{
    protected array $lookups = [];

    public function __construct(
        protected MasterInterface $masterInterface,
    ) {}

    public function validate(array $data, bool $allowLegacyExpiration = false): array
    {
        $this->lookups = [];
        $data = $this->normalizeIgnoredAdjustmentFields($data);
        $data = $this->removeUntrackedMovements($data);
        $allowEmptyMovements = ($data['movements'] ?? []) === [];

        $validator = validator($data, $this->rules($allowEmptyMovements));

        $validator->after(function (Validator $validator) use ($data, $allowLegacyExpiration): void {
            // Stop if there are structural errors (movements required, items missing fields, etc.)
            if ($validator->errors()->any()) {
                return;
            }

            $this->complexValidation($validator, $data, $allowLegacyExpiration);
        });

        $validatedData = $validator->validate();

        return [$validatedData, $this->lookups];
    }

    /**
     * Remove movements for inventory items that do not participate in stock tracking.
     *
     * Missing item IDs are intentionally preserved so that the normal existence
     * validation can report them as invalid input.
     */
    protected function removeUntrackedMovements(array $data): array
    {
        $movements = collect($data['movements'] ?? []);
        $itemIds = $movements
            ->filter(fn (mixed $movement): bool => is_array($movement) && isset($movement['item_id']))
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return $data;
        }

        $untrackedItemIds = InventoryItem::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $itemIds)
            ->where('stock_tracking_enabled', false)
            ->pluck('id')
            ->all();

        if ($untrackedItemIds === []) {
            return $data;
        }

        $data['movements'] = $movements
            ->reject(fn (mixed $movement): bool => is_array($movement)
                && in_array($movement['item_id'] ?? null, $untrackedItemIds, true))
            ->values()
            ->all();

        return $data;
    }

    protected function normalizeIgnoredAdjustmentFields(array $data): array
    {
        $transactionType = $data['transaction_type'] ?? null;
        if ($transactionType instanceof TransactionType) {
            $transactionType = $transactionType->value;
        }

        if ($transactionType !== TransactionType::Adjustment->value) {
            return $data;
        }

        foreach ($data['movements'] ?? [] as $index => $movement) {
            if (is_array($movement)) {
                unset($data['movements'][$index]['total_cost']);
                unset($data['movements'][$index]['currency_code']);
            }
        }

        return $data;
    }

    protected function rules(bool $allowEmptyMovements = false): array
    {
        return [
            'idempotency_key'  => ['required', 'string', 'min:3'],
            'reference_type'   => ['required', 'string'],
            'reference_id'     => ['required', 'string'],
            'transaction_type' => ['required', Rule::enum(TransactionType::class)],
            'metadata'         => ['nullable', 'array'],

            'movements' => $allowEmptyMovements
                ? ['present', 'array']
                : ['required', 'array', 'min:1'],

            'movements.*.item_id'        => ['required', 'string'],
            'movements.*.location_id'    => ['required', 'string'],
            'movements.*.to_location_id' => [
                'nullable',
                'string',
                'required_if:transaction_type,'.TransactionType::Transfer->value,
                'prohibited_unless:transaction_type,'.TransactionType::Transfer->value,
            ],
            'movements.*.type' => [
                'required_unless:transaction_type,'.TransactionType::Adjustment->value.','.TransactionType::Transfer->value,
                'prohibited_if:transaction_type,'.TransactionType::Adjustment->value,
                'prohibited_if:transaction_type,'.TransactionType::Transfer->value,
                Rule::enum(MovementType::class),
            ],
            'movements.*.quantity'  => ['required', 'numeric', 'gt:0'],
            'movements.*.unit_code' => ['required', 'string'],

            // Basic format/type checks
            'movements.*.total_cost'      => ['nullable', 'numeric', 'min:0'],
            'movements.*.currency_code'   => ['nullable', 'string', 'size:3'],
            'movements.*.expiration_date' => ['nullable', 'date'],
            'movements.*.metadata'        => ['nullable', 'array'],
            'movements.*.strategy'        => ['nullable', Rule::enum(DeductionStrategy::class)],
            'movements.*.stock_ids'       => ['nullable', 'array'],
            'movements.*.stock_ids.*'     => ['string'],
            'movements.*.stock_metadata'  => ['nullable', 'array'],
        ];
    }

    protected function complexValidation(Validator $validator, array $data, bool $allowLegacyExpiration = false): void
    {
        $movements = collect($data['movements']);
        $txType = $data['transaction_type'];

        if (is_string($txType)) {
            $txType = TransactionType::tryFrom($txType);
        }

        // 1. Input structure (before any database lookup)
        $this->validateStockIdStructure($validator, $movements);

        if ($validator->errors()->any()) {
            return;
        }

        // 2. Bulk Lookups (Hydrate context)
        $this->lookups = $this->performBulkLookups($movements);

        // 3. Existence & Entity Integrity (Are entities valid and requirements met?)
        $this->validateExistence(
            $validator,
            $txType,
            $movements,
            $this->lookups,
            $allowLegacyExpiration,
        );

        // 4. Structural Consistency (Duplicates, Type mismatches)
        $this->validateUniquePairs($validator, $txType, $movements);
        $this->validateTransactionTypeConsistency($validator, $txType, $movements);

        // 5. Business Logic (Only if basic integrity and structure are valid)
        if (!$validator->errors()->any()) {
            $this->validateBusinessLogic($validator, $txType, $movements, $this->lookups);
        }
    }

    protected function validateStockIdStructure(Validator $validator, Collection $movements): void
    {
        foreach ($movements as $index => $movement) {
            if (!is_array($movement)) {
                continue;
            }

            $stockIds = collect($movement['stock_ids'] ?? []);

            if ($stockIds->count() === $stockIds->unique()->count()) {
                continue;
            }

            $validator->errors()->add(
                "movements.{$index}.stock_ids",
                __('inventory::validation.duplicate_stock_ids')
            );
        }
    }

    protected function validateUniquePairs(Validator $validator, ?TransactionType $txType, Collection $movements): void
    {
        if ($txType !== TransactionType::Adjustment) {
            return;
        }

        $seen = [];
        foreach ($movements as $index => $m) {
            $key = ($m['item_id'] ?? '').':'.($m['location_id'] ?? '');
            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "movements.{$index}",
                    __('inventory::validation.adjustment_duplicate_item_location')
                );
                $validator->errors()->add(
                    "movements.{$seen[$key]}",
                    __('inventory::validation.adjustment_duplicate_item_location')
                );
            }
            $seen[$key] = $index;
        }
    }

    protected function validateTransactionTypeConsistency(Validator $validator, ?TransactionType $txType, Collection $movements): void
    {
        if (!$txType) {
            return;
        }

        $hasIn = $movements->contains(fn ($m): bool => ($m['type'] ?? null) === MovementType::In->value);
        $hasOut = $movements->contains(fn ($m): bool => ($m['type'] ?? null) === MovementType::Out->value);

        match ($txType) {
            TransactionType::In  => $hasOut && $validator->errors()->add('transaction_type', __('inventory::validation.in_transaction_only_in_movements')),
            TransactionType::Out => $hasIn && $validator->errors()->add('transaction_type', __('inventory::validation.out_transaction_only_out_movements')),
            default              => null,
        };
    }

    protected function performBulkLookups(Collection $movements): array
    {
        $organizationId = currentOrganizationId();
        $itemIds = $movements->pluck('item_id')->filter()->unique();
        $locationIds = $movements->pluck('location_id')
            ->merge($movements->pluck('to_location_id'))
            ->filter()
            ->unique();
        $currencyCodes = $movements->pluck('currency_code')->filter()->unique();
        $stockIds = $movements->pluck('stock_ids')->flatten()->filter()->unique();
        $items = InventoryItem::where('organization_id', $organizationId)
            ->whereIn('id', $itemIds)
            ->where('stock_tracking_enabled', true)
            ->get()
            ->keyBy('id');
        $unitCodes = $movements->pluck('unit_code')
            ->merge($items->pluck('base_unit_code'))
            ->filter()
            ->unique()
            ->values();

        return [
            'items'      => $items,
            'locations'  => InventoryLocation::where('organization_id', $organizationId)->whereIn('id', $locationIds)->where('is_active', true)->get()->keyBy('id'),
            'units'      => $this->masterInterface->units($unitCodes),
            'currencies' => $this->masterInterface->currencies($currencyCodes),
            'stocks'     => $stockIds->isNotEmpty() ? InventoryStock::where('organization_id', $organizationId)->whereIn('id', $stockIds)->get()->keyBy('id') : collect(),
        ];
    }

    protected function validateExistence(
        Validator $validator,
        ?TransactionType $txType,
        Collection $movements,
        array $lookups,
        bool $allowLegacyExpiration = false,
    ): void {
        foreach ($movements as $index => $m) {
            $type = $m['type'] ?? null;
            if (is_string($type)) {
                $type = MovementType::tryFrom($type);
            }

            if (isset($m['item_id']) && !$lookups['items']->has($m['item_id'])) {
                $validator->errors()->add("movements.{$index}.item_id", __('inventory::validation.item_invalid_or_inactive'));
            }
            if (isset($m['location_id']) && !$lookups['locations']->has($m['location_id'])) {
                $validator->errors()->add("movements.{$index}.location_id", __('inventory::validation.location_invalid_or_inactive'));
            }
            if (isset($m['to_location_id']) && !$lookups['locations']->has($m['to_location_id'])) {
                $validator->errors()->add("movements.{$index}.to_location_id", __('inventory::validation.location_invalid_or_inactive'));
            }
            if ($txType === TransactionType::Transfer && ($m['location_id'] ?? null) === ($m['to_location_id'] ?? null)) {
                $validator->errors()->add("movements.{$index}.to_location_id", __('inventory::validation.transfer_same_location'));
            }
            if (isset($m['unit_code']) && !$lookups['units']->has($m['unit_code'])) {
                $validator->errors()->add("movements.{$index}.unit_code", __('inventory::validation.unit_code_invalid'));
            }
            if (isset($m['currency_code']) && !$lookups['currencies']->has($m['currency_code'])) {
                $validator->errors()->add("movements.{$index}.currency_code", __('inventory::validation.currency_code_invalid'));
            }

            $item = $lookups['items']->get($m['item_id'] ?? null);
            if ($item instanceof InventoryItem) {
                $strategy = $m['strategy'] ?? null;
                if (is_string($strategy)) {
                    $strategy = DeductionStrategy::tryFrom($strategy);
                }

                if ($strategy === DeductionStrategy::Fifo && $item->is_expirable) {
                    $validator->errors()->add(
                        "movements.{$index}.strategy",
                        __('inventory::validation.fifo_expirable_prohibited')
                    );
                }

                if ($strategy === DeductionStrategy::Fefo && !$item->is_expirable) {
                    $validator->errors()->add(
                        "movements.{$index}.strategy",
                        __('inventory::validation.fefo_non_expirable_prohibited')
                    );
                }

                if ($item->deduction_strategy === DeductionStrategy::Fifo && $item->is_expirable) {
                    $validator->errors()->add(
                        "movements.{$index}.strategy",
                        __('inventory::validation.fifo_expirable_prohibited')
                    );
                }

                if ($item->deduction_strategy === DeductionStrategy::Fefo && !$item->is_expirable) {
                    $validator->errors()->add(
                        "movements.{$index}.strategy",
                        __('inventory::validation.fefo_non_expirable_prohibited')
                    );
                }

                if ($txType === TransactionType::In && $type === MovementType::In) {
                    if ($item->is_expirable && ($m['expiration_date'] ?? null) === null && !$allowLegacyExpiration) {
                        $validator->errors()->add(
                            "movements.{$index}.expiration_date",
                            __('inventory::validation.expiration_date_required_for_expirable_item')
                        );
                    }

                    if (!$item->is_expirable && ($m['expiration_date'] ?? null) !== null) {
                        $validator->errors()->add(
                            "movements.{$index}.expiration_date",
                            __('inventory::validation.expiration_date_prohibited_for_non_expirable_item')
                        );
                    }
                }
            }

            if ($type === MovementType::In) {
                if ($txType === TransactionType::In) {
                    if (!isset($m['total_cost'])) {
                        $validator->errors()->add("movements.{$index}.total_cost", __('inventory::validation.in_total_cost_required'));
                    }

                    if (!isset($m['currency_code'])) {
                        $validator->errors()->add("movements.{$index}.currency_code", __('inventory::validation.in_currency_code_required'));
                    }
                }

                if ($txType === TransactionType::Transfer) {
                    if (isset($m['total_cost'])) {
                        $validator->errors()->add("movements.{$index}.total_cost", __('inventory::validation.transfer_in_total_cost_prohibited'));
                    }

                    if (isset($m['currency_code'])) {
                        $validator->errors()->add("movements.{$index}.currency_code", __('inventory::validation.transfer_in_currency_code_prohibited'));
                    }

                    if (isset($m['expiration_date'])) {
                        $validator->errors()->add("movements.{$index}.expiration_date", __('inventory::validation.transfer_in_expiration_date_prohibited'));
                    }
                }

                if (isset($m['strategy'])) {
                    $validator->errors()->add("movements.{$index}.strategy", __('inventory::validation.in_strategy_prohibited'));
                }

                if (isset($m['stock_ids'])) {
                    $validator->errors()->add("movements.{$index}.stock_ids", __('inventory::validation.in_stock_ids_prohibited'));
                }

                if ($txType !== TransactionType::In && ($m['stock_metadata'] ?? null) !== null) {
                    $validator->errors()->add("movements.{$index}.stock_metadata", __('inventory::validation.stock_metadata_in_only'));
                }
            }

            if ($txType === TransactionType::Transfer) {
                foreach (['total_cost', 'currency_code', 'expiration_date', 'stock_metadata'] as $field) {
                    if (isset($m[$field])) {
                        $validator->errors()->add("movements.{$index}.{$field}", __('inventory::validation.transfer_field_prohibited', ['field' => $field]));
                    }
                }
            }

            if ($type === MovementType::Out) {
                if (($m['stock_metadata'] ?? null) !== null) {
                    $validator->errors()->add("movements.{$index}.stock_metadata", __('inventory::validation.out_stock_metadata_prohibited'));
                }

                if (isset($m['total_cost'])) {
                    $validator->errors()->add("movements.{$index}.total_cost", __('inventory::validation.out_total_cost_prohibited'));
                }

                if (isset($m['currency_code'])) {
                    $validator->errors()->add("movements.{$index}.currency_code", __('inventory::validation.out_currency_code_prohibited'));
                }

                if (isset($m['expiration_date'])) {
                    $validator->errors()->add("movements.{$index}.expiration_date", __('inventory::validation.out_expiration_date_prohibited'));
                }
            }

            if (isset($m['total_cost'], $m['currency_code']) && $lookups['currencies']->has($m['currency_code'])) {
                $currency = $lookups['currencies']->get($m['currency_code']);
                $precision = $currency->precision;

                $v = validator(
                    ['total_cost' => $m['total_cost']],
                    ['total_cost' => "decimal:0,{$precision}"]
                );

                if ($v->fails()) {
                    $validator->errors()->add(
                        "movements.{$index}.total_cost",
                        __('inventory::validation.total_cost_precision', [
                            'currency_code' => $m['currency_code'],
                            'precision'     => $precision,
                        ])
                    );
                }
            }

            // Stock IDs validation
            $resolvedStrategy = $m['strategy'] ?? null;
            if (is_string($resolvedStrategy)) {
                $resolvedStrategy = DeductionStrategy::tryFrom($resolvedStrategy);
            }

            $resolvedStrategy ??= $item instanceof InventoryItem
                ? $item->deduction_strategy
                : null;
            $resolvedStrategy ??= $item instanceof InventoryItem && $item->is_expirable
                ? DeductionStrategy::Fefo
                : DeductionStrategy::Fifo;

            if ($resolvedStrategy === DeductionStrategy::Manual && empty($m['stock_ids'])) {
                $validator->errors()->add("movements.{$index}.stock_ids", __('inventory::validation.manual_stock_ids_required'));
            }

            if (!empty($m['stock_ids']) && ($type === MovementType::Out || $txType === TransactionType::Transfer)) {
                foreach ($m['stock_ids'] as $sid) {
                    $stock = $lookups['stocks']->get($sid);
                    if (!$stock) {
                        $validator->errors()->add("movements.{$index}.stock_ids", __('inventory::validation.stock_id_invalid', ['stock_id' => $sid]));
                    } elseif ($stock->item_id !== ($m['item_id'] ?? null) || $stock->location_id !== ($m['location_id'] ?? null)) {
                        $validator->errors()->add("movements.{$index}.stock_ids", __('inventory::validation.stock_id_wrong_scope', ['stock_id' => $sid]));
                    }
                }
            }
        }
    }

    protected function validateBusinessLogic(Validator $validator, ?TransactionType $txType, Collection $movements, array $lookups): void
    {
        $items = $lookups['items'];

        $this->checkUnitGroups($validator, $movements, $items, $lookups['units']);

        // Transfer quantities are validated per source-to-destination line and
        // therefore do not require a global IN/OUT balance.
    }

    /**
     * @param  Collection<string, Unit>  $units
     */
    protected function checkUnitGroups(Validator $validator, Collection $movements, Collection $items, Collection $units): void
    {
        foreach ($movements as $index => $m) {
            $item = $items->get($m['item_id']);
            if (!$item || !$item->base_unit_code) {
                continue;
            }

            $baseUnit = $units->get($item->base_unit_code);
            $providedUnit = $units->get($m['unit_code']);

            if (!$baseUnit) {
                throw new BaseUnitNotFoundException($item->id, $item->base_unit_code);
            }

            if (!$providedUnit) {
                $validator->errors()->add("movements.{$index}.unit_code", __('inventory::validation.unit_code_invalid'));
            }

            if ($baseUnit->ratio !== 1) {
                throw new BaseUnitRatioIntegrityException($item->id, $item->base_unit_code);
            }

            if ($providedUnit && $baseUnit->group_id !== $providedUnit->group_id) {
                $validator->errors()->add("movements.{$index}.unit_code", __('inventory::validation.unit_group_mismatch', [
                    'unit_code'      => $m['unit_code'],
                    'base_unit_code' => $item->base_unit_code,
                ]));
            }
        }
    }

    protected function checkTransferBalance(Validator $validator, Collection $movements, Collection $items): void
    {
        $grouped = $movements->groupBy('item_id');

        foreach ($grouped as $itemId => $itemMovements) {
            $item = $items->get($itemId);
            if (!$item) {
                continue;
            }

            $totalIn = '0';
            $totalOut = '0';

            foreach ($itemMovements as $m) {
                $qtyInBase = $this->masterInterface->convertUnit((string) $m['quantity'], $m['unit_code'], $item->base_unit_code);
                if ($m['type'] === MovementType::In->value) {
                    $totalIn = bcadd($totalIn, $qtyInBase, 10);
                } else {
                    $totalOut = bcadd($totalOut, $qtyInBase, 10);
                }
            }

            if (bccomp($totalIn, $totalOut, 10) !== 0) {
                $validator->errors()->add('movements', __('inventory::validation.transfer_imbalance', [
                    'item_id'        => $itemId,
                    'total_in'       => $totalIn,
                    'total_out'      => $totalOut,
                    'base_unit_code' => $item->base_unit_code,
                ]));
            }
        }
    }
}
