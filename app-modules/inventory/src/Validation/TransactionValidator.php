<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Validation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Support\UnitCache;

class TransactionValidator
{
    protected array $lookups = [];

    public function __construct(
        protected UnitCache $unitCache
    ) {}

    public function validate(array $data): array
    {
        $validator = validator($data, $this->rules());

        $validator->after(function (Validator $validator) use ($data) {
            // Stop if there are structural errors (movements required, items missing fields, etc.)
            if ($validator->errors()->any()) {
                return;
            }

            $this->complexValidation($validator, $data);
        });

        $validatedData = $validator->validate();

        return [$validatedData, $this->lookups];
    }

    protected function rules(): array
    {
        return [
            'reference_type'   => ['required', 'string'],
            'reference_id'     => ['required', 'string'],
            'transaction_type' => ['required', Rule::enum(TransactionType::class)],
            'metadata'         => ['nullable', 'array'],

            'movements' => ['required', 'array', 'min:1'],

            'movements.*.item_id'     => ['required', 'string', 'bail'],
            'movements.*.location_id' => ['required', 'string', 'bail'],
            'movements.*.type'        => [
                'required_unless:transaction_type,adjustment',
                'nullable',
                Rule::enum(MovementType::class),
                'bail',
            ],
            'movements.*.quantity'  => ['required', 'numeric', 'gt:0', 'bail'],
            'movements.*.unit_code' => ['required', 'string', 'bail'],

            // Basic format/type checks
            'movements.*.unit_cost'       => ['nullable', 'numeric', 'min:0'],
            'movements.*.currency_code'   => ['nullable', 'string', 'size:3'],
            'movements.*.peremption_date' => ['nullable', 'date'],
            'movements.*.metadata'        => ['nullable', 'array'],
            'movements.*.strategy'        => ['nullable', Rule::enum(DeductionStrategy::class)],
            'movements.*.stock_ids'       => ['nullable', 'array'],
        ];
    }

    protected function complexValidation(Validator $validator, array $data): void
    {
        $movements = collect($data['movements']);
        $txType = $data['transaction_type'];

        if (is_string($txType)) {
            $txType = TransactionType::tryFrom($txType);
        }

        // 1. Unique Pairs (Item + Location)
        $this->validateUniquePairs($validator, $movements);

        // 2. Transaction Type vs Movement Types
        $this->validateTransactionTypeConsistency($validator, $txType, $movements);

        // 3. Bulk Lookups (Cache)
        $this->lookups = $this->performBulkLookups($movements);

        // 4. Validate existence of entities with precise index errors
        $this->validateExistence($validator, $movements, $this->lookups, $txType);

        // 5. Business Logic (only if structural lookups pass)
        if (!$validator->errors()->any()) {
            $this->validateBusinessLogic($validator, $txType, $movements, $this->lookups);
        }
    }

    protected function validateUniquePairs(Validator $validator, Collection $movements): void
    {
        $seen = [];
        foreach ($movements as $index => $m) {
            $key = ($m['item_id'] ?? '').':'.($m['location_id'] ?? '');
            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "movements.{$index}",
                    'The same item cannot appear multiple times for the same location in a single transaction.'
                );
                $validator->errors()->add(
                    "movements.{$seen[$key]}",
                    'The same item cannot appear multiple times for the same location in a single transaction.'
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

        $hasIn = $movements->contains(fn ($m) => ($m['type'] ?? null) === MovementType::In->value);
        $hasOut = $movements->contains(fn ($m) => ($m['type'] ?? null) === MovementType::Out->value);

        match ($txType) {
            TransactionType::In       => $hasOut && $validator->errors()->add('transaction_type', "An 'IN' transaction can only contain 'in' movements."),
            TransactionType::Out      => $hasIn && $validator->errors()->add('transaction_type', "An 'OUT' transaction can only contain 'out' movements."),
            TransactionType::Transfer => (!$hasIn || !$hasOut) && $validator->errors()->add('transaction_type', "A 'TRANSFER' transaction must have at least one 'in' and one 'out' movement."),
            default                   => null,
        };
    }

    protected function performBulkLookups(Collection $movements): array
    {
        $itemIds = $movements->pluck('item_id')->filter()->unique();
        $locationIds = $movements->pluck('location_id')->filter()->unique();
        $unitCodes = $movements->pluck('unit_code')->filter()->unique();
        $currencyCodes = $movements->filter(fn ($m) => ($m['type'] ?? null) === MovementType::In->value)
            ->pluck('currency_code')->filter()->unique();
        $stockIds = $movements->pluck('stock_ids')->flatten()->filter()->unique();

        return [
            'items'      => InventoryItem::whereIn('id', $itemIds)->where('is_active', true)->get()->keyBy('id'),
            'locations'  => InventoryLocation::whereIn('id', $locationIds)->where('is_active', true)->get()->keyBy('id'),
            'units'      => DB::table('master_units')->whereIn('code', $unitCodes)->get()->keyBy('code'),
            'currencies' => $currencyCodes->isNotEmpty() ? DB::table('master_currencies')->whereIn('code', $currencyCodes)->get()->keyBy('code') : collect(),
            'stocks'     => $stockIds->isNotEmpty() ? InventoryStock::whereIn('id', $stockIds)->get()->keyBy('id') : collect(),
        ];
    }

    protected function validateExistence(Validator $validator, Collection $movements, array $lookups, ?TransactionType $txType): void
    {
        foreach ($movements as $index => $m) {
            $type = $m['type'] ?? null;
            if (is_string($type)) {
                $type = MovementType::tryFrom($type);
            }

            if (isset($m['item_id']) && !$lookups['items']->has($m['item_id'])) {
                $validator->errors()->add("movements.{$index}.item_id", 'The selected item is invalid or inactive.');
            }
            if (isset($m['location_id']) && !$lookups['locations']->has($m['location_id'])) {
                $validator->errors()->add("movements.{$index}.location_id", 'The selected location is invalid or inactive.');
            }
            if (isset($m['unit_code']) && !$lookups['units']->has($m['unit_code'])) {
                $validator->errors()->add("movements.{$index}.unit_code", 'The selected unit code is invalid.');
            }
            if (isset($m['currency_code']) && !$lookups['currencies']->has($m['currency_code'])) {
                $validator->errors()->add("movements.{$index}.currency_code", 'The selected currency code is invalid.');
            }

            if ($type === MovementType::In) {
                if ($txType === TransactionType::In) {
                    if (!isset($m['unit_cost'])) {
                        $validator->errors()->add("movements.{$index}.unit_cost", "The unit cost is required for 'in' movements in an 'IN' transaction.");
                    }

                    if (!isset($m['currency_code'])) {
                        $validator->errors()->add("movements.{$index}.currency_code", "The currency code is required for 'in' movements in an 'IN' transaction.");
                    }
                }

                if ($txType === TransactionType::Transfer) {
                    if (isset($m['unit_cost'])) {
                        $validator->errors()->add("movements.{$index}.unit_cost", "The unit cost is prohibited for 'in' movements in a 'TRANSFER' transaction (it is inherited from the source).");
                    }

                    if (isset($m['currency_code'])) {
                        $validator->errors()->add("movements.{$index}.currency_code", "The currency code is prohibited for 'in' movements in a 'TRANSFER' transaction.");
                    }

                    if (isset($m['peremption_date'])) {
                        $validator->errors()->add("movements.{$index}.peremption_date", "The peremption date is prohibited for 'in' movements in a 'TRANSFER' transaction.");
                    }
                }

                if (
                    ($txType === TransactionType::In || $txType === TransactionType::Adjustment)
                    && isset($m['unit_cost'], $m['currency_code'])
                    && $lookups['currencies']->has($m['currency_code'])
                ) {
                    $currency = $lookups['currencies']->get($m['currency_code']);
                    $precision = $currency->precision;

                    $v = validator(
                        ['unit_cost' => $m['unit_cost']],
                        ['unit_cost' => "decimal:0,{$precision}"]
                    );

                    if ($v->fails()) {
                        $validator->errors()->add(
                            "movements.{$index}.unit_cost",
                            "The unit cost for currency {$m['currency_code']} must have at most {$precision} decimal places."
                        );
                    }
                }

                if ($txType !== TransactionType::Adjustment && isset($m['strategy'])) {
                    $validator->errors()->add("movements.{$index}.strategy", "Stock deduction strategy is prohibited for 'in' movements.");
                }

                if ($txType !== TransactionType::Adjustment && isset($m['stock_ids'])) {
                    $validator->errors()->add("movements.{$index}.stock_ids", "Stock IDs are prohibited for 'in' movements.");
                }
            }

            if ($type === MovementType::Out) {
                if ($txType !== TransactionType::Adjustment && isset($m['unit_cost'])) {
                    $validator->errors()->add("movements.{$index}.unit_cost", "The unit cost is prohibited for 'out' movements.");
                }

                if ($txType !== TransactionType::Adjustment && isset($m['currency_code'])) {
                    $validator->errors()->add("movements.{$index}.currency_code", "The currency code is prohibited for 'out' movements.");
                }

                if ($txType !== TransactionType::Adjustment && isset($m['peremption_date'])) {
                    $validator->errors()->add("movements.{$index}.peremption_date", "The peremption date is prohibited for 'out' movements.");
                }
            }

            // Stock IDs validation
            $strategy = $m['strategy'] ?? null;
            if (is_string($strategy)) {
                $strategy = DeductionStrategy::tryFrom($strategy);
            }

            if ($strategy === DeductionStrategy::Manual && empty($m['stock_ids'])) {
                $validator->errors()->add("movements.{$index}.stock_ids", 'Stock IDs are required when strategy is manual.');
            }

            if (!empty($m['stock_ids']) && $type === MovementType::Out) {
                foreach ($m['stock_ids'] as $sid) {
                    $stock = $lookups['stocks']->get($sid);
                    if (!$stock) {
                        $validator->errors()->add("movements.{$index}.stock_ids", "Stock ID {$sid} is invalid.");
                    } elseif ($stock->item_id !== ($m['item_id'] ?? null) || $stock->location_id !== ($m['location_id'] ?? null)) {
                        $validator->errors()->add("movements.{$index}.stock_ids", "Stock ID {$sid} does not belong to the correct item and location.");
                    }
                }
            }
        }

        $currencies = $movements->pluck('currency_code')->filter()->unique();
        if ($currencies->count() > 1) {
            $validator->errors()->add('movements', 'All movements in a transaction must use the same currency code.');
        }
    }

    protected function validateBusinessLogic(Validator $validator, ?TransactionType $txType, Collection $movements, array $lookups): void
    {
        $items = $lookups['items'];

        $this->checkUnitGroups($validator, $movements, $items);

        if ($txType === TransactionType::Transfer) {
            $this->checkTransferBalance($validator, $movements, $items);
        }
    }

    protected function checkUnitGroups(Validator $validator, Collection $movements, Collection $items): void
    {
        foreach ($movements as $index => $m) {
            $item = $items->get($m['item_id']);
            if (!$item || !$item->base_unit_code) {
                continue;
            }

            $baseUnit = $this->unitCache->getByCode($item->base_unit_code);
            $providedUnit = $this->unitCache->getByCode($m['unit_code']);

            if (!$baseUnit || !$providedUnit) {
                continue;
            }

            if ($baseUnit->ratio !== 1) {
                $validator->errors()->add("movements.{$index}.item_id", "The item base unit {$item->base_unit_code} is invalid.");
            }

            if ($baseUnit->group_id !== $providedUnit->group_id) {
                $validator->errors()->add("movements.{$index}.unit_code", "Unit {$m['unit_code']} belongs to a different group than item base unit {$item->base_unit_code}.");
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
                $qtyInBase = convertUnit((string) $m['quantity'], $m['unit_code'], $item->base_unit_code);
                if ($m['type'] === MovementType::In->value) {
                    $totalIn = bcadd($totalIn, $qtyInBase, 10);
                } else {
                    $totalOut = bcadd($totalOut, $qtyInBase, 10);
                }
            }

            if (bccomp($totalIn, $totalOut, 10) !== 0) {
                $validator->errors()->add('movements', "Transfer imbalance for item {$itemId}. Total IN: {$totalIn}, Total OUT: {$totalOut} (in base unit {$item->base_unit_code}).");
            }
        }
    }
}
