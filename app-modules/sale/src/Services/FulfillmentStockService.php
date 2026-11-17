<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Sale\Data\PlannedFulfillmentLine;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Shared\Support\Decimal;

/**
 * Moves the stock of checked fulfillment lines through inventory, inside the caller's transaction.
 * Inventory's refusals come back on the fields of the request, or as a sale refusal naming the line.
 */
final readonly class FulfillmentStockService
{
    public function __construct(
        private InventoryInterface $inventoryInterface,
        private MasterInterface $masterInterface,
    ) {}

    /**
     * Takes the tracked lines out of their source locations, in one inventory transaction.
     *
     * @param  list<PlannedFulfillmentLine>  $lines
     *
     * @throws SaleException If a location lacks the stock by the time it is locked.
     *
     * @return array{transaction_id: string|null, costs: array<int, int>} the cost of each line that moved, by its index
     */
    public function takeOut(string $fulfillmentId, array $lines): array
    {
        return $this->record($fulfillmentId, 'sale_fulfillment', $fulfillmentId, TransactionType::Out, $lines, collect());
    }

    /**
     * Moves the tracked lines from their warehouses to the warehouses' customer hold locations.
     *
     * @param  list<PlannedFulfillmentLine>  $lines
     * @param  Collection<string, StockLocation>  $holdLocations  keyed by warehouse id
     *
     * @throws SaleException If a warehouse lacks the stock by the time it is locked.
     */
    public function setAside(string $saleId, array $lines, Collection $holdLocations): ?string
    {
        return $this->record("sale-hold:{$saleId}", 'sale_sale', $saleId, TransactionType::Transfer, $lines, $holdLocations)['transaction_id'];
    }

    /**
     * Moves what remains set aside back from each hold location to its warehouse.
     *
     * @param  list<array{item: InventoryItem, from: InventoryLocation, to: InventoryLocation, quantity: int}>  $releases
     */
    public function release(string $saleId, array $releases): string
    {
        return $this->inventoryInterface->recordTransaction([
            'idempotency_key'  => "sale-release:{$saleId}",
            'reference_type'   => 'sale_sale',
            'reference_id'     => $saleId,
            'transaction_type' => TransactionType::Transfer->value,
            'metadata'         => ['reason' => 'sale_cancelled', 'sale_id' => $saleId],
            'movements'        => array_map(fn (array $release): array => [
                'item_id'        => $release['item']->id,
                'location_id'    => $release['from']->id,
                'to_location_id' => $release['to']->id,
                'quantity'       => $release['quantity'],
                'unit_code'      => $release['item']->base_unit_code,
            ], $releases),
        ], [])->id;
    }

    /** Gives back the stock of a completed fulfillment, into the lots it left. */
    public function giveBack(string $transactionId, string $fulfillmentId): string
    {
        return $this->inventoryInterface->reverseTransaction(
            $transactionId,
            ['reason' => 'sale_fulfillment_cancelled', 'fulfillment_id' => $fulfillmentId],
            [],
        )->id;
    }

    /**
     * @param  list<PlannedFulfillmentLine>  $lines
     * @param  Collection<string, StockLocation>  $destinations  keyed by warehouse id, for a transfer
     *
     * @throws SaleException
     *
     * @return array{transaction_id: string|null, costs: array<int, int>}
     */
    private function record(string $key, string $referenceType, string $referenceId, TransactionType $type, array $lines, Collection $destinations): array
    {
        $movements = [];
        $errorKeyMap = [];

        foreach ($lines as $index => $line) {
            if (!$line->isTracked || !$line->source instanceof StockLocation) {
                continue;
            }
            /** @var InventoryItem $inventoryItem */
            $inventoryItem = $line->catalogItem->inventoryItem;
            $movement = [
                'item_id'     => $inventoryItem->id,
                'location_id' => $this->inventoryLocation($line->source)->id,
                'quantity'    => $line->quantity,
                'unit_code'   => $inventoryItem->base_unit_code,
                'metadata'    => ['line' => $index],
            ];
            $movement += $type === TransactionType::Transfer
                ? ['to_location_id' => $this->inventoryLocation($destinations->get((string) $line->warehouse?->id))->id]
                : ['type' => MovementType::Out->value];
            if ($line->stockIds !== null) {
                $movement += ['strategy' => DeductionStrategy::Manual->value, 'stock_ids' => $line->stockIds];
            }

            $n = count($movements);
            $movements[] = $movement;
            $errorKeyMap += [
                "movements.{$n}.quantity"    => "{$line->path}.quantity",
                "movements.{$n}.location_id" => "{$line->path}.stock_location_id",
                "movements.{$n}.stock_ids"   => "{$line->path}.stock_ids",
                "movements.{$n}.stock_ids.*" => "{$line->path}.stock_ids.*",
            ];
        }

        if ($movements === []) {
            return ['transaction_id' => null, 'costs' => []];
        }

        try {
            $transaction = $this->inventoryInterface->recordTransaction([
                'idempotency_key'  => $key,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'transaction_type' => $type->value,
                'movements'        => $movements,
            ], ['movements'], $errorKeyMap);
        } catch (InsufficientStockException $shortage) {
            throw $this->shortage($shortage, $lines);
        }

        $costs = [];
        foreach ($transaction->movements as $movement) {
            /** @var InventoryMovement $movement */
            $index = $movement->metadata['line'] ?? null;
            if (is_int($index) && $movement->movement_type === MovementType::Out) {
                $costs[$index] = ($costs[$index] ?? 0) + $movement->total_cost;
            }
        }

        return ['transaction_id' => $transaction->id, 'costs' => $costs];
    }

    private function inventoryLocation(?StockLocation $location): InventoryLocation
    {
        /** @var InventoryLocation */
        return $location?->inventoryLocation;
    }

    /**
     * Names the line, the warehouse and the quantities in the unit sold in, instead of inventory's ids and base unit.
     *
     * @param  list<PlannedFulfillmentLine>  $lines
     */
    private function shortage(InsufficientStockException $shortage, array $lines): SaleException
    {
        $context = $shortage->context();
        foreach ($lines as $line) {
            if ($line->source instanceof StockLocation
                && $line->catalogItem->inventoryItem?->id === $context['item_id']
                && $line->source->inventoryLocation?->id === $context['location_id']) {
                $unit = $line->saleLine->display_unit_code;
                $inUnit = fn (string $quantity): string => Decimal::normalize($this->masterInterface->convertUnit($quantity, (string) $context['unit_code'], $unit));

                return SaleException::stockInsufficient([__('sale::exceptions.stock_shortage', [
                    'item'      => $line->label(),
                    'location'  => (string) $line->warehouse?->name,
                    'asked'     => $inUnit((string) $context['requested']),
                    'available' => $inUnit((string) $context['available']),
                    'unit'      => $unit,
                ])]);
            }
        }

        return SaleException::stockInsufficient([$shortage->getMessage()]);
    }
}
