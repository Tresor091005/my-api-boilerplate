# Inventory Module

The Inventory Module is a robust, location-aware stock management system designed to handle the complexities of tracking items across multiple warehouses or storage areas. It abstracts away the low-level logic of transactions, stock levels, lot tracking (FIFO/FEFO), and multi-unit conversions.

## Core Philosophy

- **Everything is an Item/Location**: Any model can become an "Inventory Item" (e.g., a Product, a Spare Part) or an "Inventory Location" (e.g., a Warehouse, a Shelf, a Service Van) by implementing the appropriate interface.
- **Transactional Integrity (Ledger Principle)**: All stock changes are recorded as part of an immutable `Transaction`. Stock is managed like a financial ledger, allowing you to reconstitute the exact stock state at any given date by replaying movements.
- **Data Persistence**: Both `InventoryItem` and `InventoryLocation` use Soft Deletes. This ensures that historical data, movements, and transactions are never lost even if the domain model is removed.
- **Separation of Concerns**: Writing (mutations) is handled by the `InventoryInterface`, while Reading (queries) is optimized through the `InventoryQueryService`.
- **Organization Scope**: Inventory records belong to the current organization resolved by `getPermissionsTeamId()`. Inventory routes require the `auth.api` context, and all referenced itemable, external, item, location, and stock records must belong to that organization.

## Key Concepts

- **InventoryItem**: The bridge between your domain model (e.g., Product) and the inventory system.
- **InventoryLocation**: Where items are stored. Can be nested or tied to external entities.
- **InventoryStock (Lots)**: A specific quantity of an item in a location, potentially with an expiration date and specific cost.
- **InventoryTransaction**: A high-level record of a stock operation (e.g., "Purchase Order #123").
- **InventoryMovement**: The granular changes to stock within a transaction (e.g., "Adding 50 units of SKU-A to Warehouse 1").
- **Reversal**: A normal counter-entry transaction that inverts a supported `IN`, `OUT`, or linked `TRANSFER` transaction without rewriting historical stock state.

## Unit Management

The module handles multi-unit inventory seamlessly through integration with the `Master` module:
- **Base Units**: Every `InventoryItem` has a "Base Unit" (defined by its Unit Group). All internal stock levels (`remaining`) are stored in this base unit to ensure precision and consistency.
- **Automatic Conversion**: You can record transactions in any unit belonging to the item's unit group (e.g., receiving in "Boxes" while the base unit is "Pieces"). The system automatically calculates the base quantity using conversion ratios.
- **Precision**: Calculations use BCMath to prevent rounding errors during complex unit conversions.

## Metadata Support

You can attach custom JSON metadata to various levels of the inventory ledger to store domain-specific information (e.g., batch numbers, external IDs, notes):
- **Transaction Metadata**: High-level info about the overall operation.
- **Movement Metadata**: Specific information about one persisted movement or event.
- **Stock (Lot) Metadata**: Persistent information that belongs to the physical lot.

These fields have different ownership. `movement.metadata` is never implicitly copied into `inventory_stocks.metadata`.

### Stock metadata rules

- Regular `IN`: optional `stock_metadata` initializes the newly created stock. If omitted, the stock metadata is `null`.
- Positive `ADJUSTMENT`: optional `stock_metadata` initializes the newly created adjustment stock.
- Negative `ADJUSTMENT`: `stock_metadata` is accepted but ignored because no stock is created.
- `OUT`: `stock_metadata` is not accepted. The current stock metadata is captured automatically in `stock_metadata_snapshot` on the outbound movement.
- `TRANSFER`: the caller does not provide destination `stock_metadata`; the destination stock inherits the source stock metadata. The inbound transfer movement keeps its own `movement.metadata` separately.

Stock metadata can later be replaced through `PATCH /v1/inventory/stocks/{stock}`. The endpoint only changes the metadata field and does not alter quantity, cost, currency, expiration, item, location, or unit.

## Exact Cost Tracking

Inbound operations accept a `total_cost` rather than relying on a rounded unit cost:

```php
'quantity'      => 3,
'total_cost'    => 100.01,
'currency_code' => 'USD',
```

The module converts the amount to minor units and derives:

```text
unit_cost      = floor(total_cost / quantity)
cost_remainder = total_cost % quantity
```

The calculation always uses the item's base unit. The input quantity is first
converted to that base unit, and the derived `unit_cost` is therefore the cost
per base unit—not necessarily the cost per unit supplied by the caller.

For example, if `2 BOX = 20 PIECES` and the total cost is `150.00 USD`, the
stored unit cost is `7.50 USD` per `PIECE`.

The complete remainder is consumed by the first positive `OUT` deduction from the lot. Every movement persists only its exact `total_cost`, so transfers and reversals do not lose fractional minor units. Stock resources expose the derived `unit_cost`, the currency-formatted `cost_remainder`, and the total cost of the remaining stock. Persistence remains in minor units.

Positive adjustments do not accept a caller-selected cost. The caller must provide
`currency_code`, and the new quantity is valued using the weighted average cost
of the remaining stock for that item, location, and currency. If no remaining
stock exists in that currency, the adjustment is rejected. `total_cost`, when
present in an adjustment payload, is ignored. Negative adjustments ignore cost
and currency inputs and continue to use the configured deduction strategy.

Transactions may contain movements in different currencies. Values are never
averaged across currencies; each movement and stock lot retains its own currency.

## Deduction Strategies

When recording `Out` or `Transfer` movements, the system must decide which physical lots to deplete. Three strategies are supported:

- **FIFO (First-In, First-Out)**: Depletes the oldest lots first (based on creation date). This is the default strategy.
- **FEFO (First-Expired, First-Out)**: Depletes lots with the earliest expiration dates first.
- **Manual**: Allows you to specify exactly which lots to deplete by providing a list of `stock_ids`. This is useful for high-value items or specific warehouse picking requirements.

### Transfer & Distribution Logic

A transfer movement represents one route from `location_id` to `to_location_id`:

```php
[
    'item_id'        => $itemId,
    'location_id'    => $sourceLocationId,
    'to_location_id' => $destinationLocationId,
    'quantity'       => 60,
    'unit_code'      => 'PCS',
    'strategy'       => 'manual',
    'stock_ids'      => [$stockId],
]
```

The caller never supplies `type`, costs, currency, expiration, `stock_metadata`,
or `link_id` for a transfer. The service generates one UUID `link_id` per
route and stores it on every persisted `OUT` and `IN` movement produced by
that route. `strategy` and `stock_ids` control the source deduction.
Destination stocks inherit the source stock metadata and the persisted exact
cost.

## Keeping Digital & Physical in Sync

While this module provides a mathematically perfect ledger, **physical logistics are messy**. Real-world discrepancies (damage, miscounts, theft) are inevitable.

- **Manual Corrections**: Use `Out` transactions for waste/shrinkage and `Adjustment` transactions for periodic stock-takes.
- **Human Oversight**: The digital system should be treated as a reflection of physical reality, not a replacement for it. Regular physical audits are essential to ensure the ledger remains accurate.

## Getting Started

### 1. Preparing your Models

To make a model "Inventoryable", implement the `HasInventoryItem` or `HasInventoryLocation` interface and use the corresponding trait.

Tenant-aware models must expose their `organization_id` through `getOrganizationId()`.

**Advanced Integration**: By using the traits, your domain models gain direct access to deep relationships (via `staudenmeir/eloquent-has-many-deep`):
- `$product->inventoryItemStocks()`: All lots for this product across all locations.
- `$product->activeInventoryItemStocks()`: Only lots with remaining quantity > 0.
- `$warehouse->inventoryLocationMovements()`: All movements that occurred in this location.

```php
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Traits\InteractsWithInventoryItem;

class Product extends Model implements HasInventoryItem
{
    use InteractsWithInventoryItem;

    public function getSku(): string { return $this->sku; }
    public function getUnitGroupId(): string { return $this->unit_group_id; }
    public function getOrganizationId(): string { return $this->organization_id; }

    /**
     * Define which fields are shared with the Inventory API Resources.
     */
    public function toInventoryItemableSummary(): array {
        return ['name' => $this->name, 'category' => $this->category->name];
    }
}
```

### 2. Writing Operations (InventoryInterface)

#### Recording an Incoming Shipment with Separate Metadata
```php
$inventory->recordTransaction([
    'idempotency_key' => 'purchase-order-'.$po->id,
    'reference_type' => 'purchase_order',
    'reference_id' => $po->id,
    'transaction_type' => TransactionType::In,
    'metadata' => ['supplier_invoice' => 'INV-2024-001'],
    'movements' => [
        [
            'item_id' => $inventoryItemId,
            'location_id' => $inventoryLocationId,
            'quantity' => 10,
            'unit_code' => 'BOX', // System converts this to base unit automatically
            'total_cost' => 1500.00,
            'currency_code' => 'USD',
            'metadata' => ['source' => 'supplier'], // Movement/event metadata
            'stock_metadata' => ['batch_number' => 'B-12345'], // New stock metadata
        ]
    ]
]);
```

### 3. Updating Stock Metadata

Stock metadata is managed independently from transaction creation:

```http
PATCH /v1/inventory/stocks/{stock}
Content-Type: application/json

{
    "metadata": {
        "batch_number": "B-12345",
        "status": "quarantine"
    }
}
```

The request replaces the complete metadata object. Sending `"metadata": null` clears it.

### 4. Reversing a Transaction

Regular `IN`, `OUT`, and linked `TRANSFER` transactions can be reversed. A reversal is a separate ledger transaction and does not restore the original stock row directly:

```php
$reversal = $inventory->reverseTransaction(
    originalTransactionId: $transaction->id,
    metadata: ['reason' => 'order_cancelled'],
);
```

The reversal uses the deterministic idempotency key `{$originalTransactionId}:reverse` and stores `reversal_of_transaction_id`.

The original transaction metadata is not copied automatically. The reversal
uses exactly the metadata supplied by the caller.

- Original `IN` → manual `OUT` against the original stock ID.
- Original `OUT` → new `IN` using the persisted quantity, cost, currency, expiration, movement metadata, and outbound `stock_metadata_snapshot`.
- The original stock's `remaining` value is never directly restored.
- Repeating the same request returns the existing reversal; a different payload with the same key fails idempotency validation.
- A `TRANSFER` reversal groups movements by `link_id` and reverses each source-to-destination route independently. Transfers with missing or inconsistent links are not reversible. `ADJUSTMENT` reversal is not supported.

To check whether an operation is possible without persisting anything, use the
preview methods:

```php
$inventory->previewTransaction($payload);
$inventory->previewReversal($transaction->id, ['reason' => 'order_cancelled']);
```

They return `void` on success and propagate the same validation or business
exception as the real operation on failure. The preview runs the complete
calculation pipeline inside a rollback-only transaction. It does not leave
transactions, movements, stocks, stock changes, or model events behind.

#### Mapping validation error keys

Transaction validation keeps package paths by default. Callers with a different
payload shape may override those paths per operation:

```php
$inventory->recordTransaction(
    data: $payload,
    errorKeyMap: [
        'movements.*.item_id'     => 'lines.*.product_id',
        'movements.*.location_id' => 'locations.*.id',
    ],
);
```

The same `errorKeyMap` is available on `reverseTransaction`. Wildcards are
replaced in order and their count must remain unchanged. A mapping for
`movements.*.stock_ids` also covers descendant keys such as
`movements.0.stock_ids.2`, allowing the caller to intentionally omit the
internal stock index from its own error key.

### 5. Reading Operations (InventoryQueryService)

Optimized for retrieval and API consumption. It automatically converts internal minor units back to decimal strings.

#### Get Stock for an Item
```php
// Returns ItemStockViewData with total remaining and per-location breakdown
$stock = $queryService->getItemStock($product);
echo $stock->totalRemaining; // 150
```

## API Reference

The module provides read endpoints and a dedicated stock metadata mutation endpoint:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/v1/inventory/items` | List all inventory items |
| GET | `/v1/inventory/items/{item}/stock` | Simple aggregated stock for an item |
| GET | `/v1/inventory/items/{item}/value` | Financial value of an item's stock |
| GET | `/v1/inventory/items/{item}/movements` | Transaction history for an item |
| GET | `/v1/inventory/locations/{location}/stock` | List all items and quantities in a location |
| GET | `/v1/inventory/stock/summary` | Global summary of stock across all items/locations |
| GET | `/v1/inventory/transactions` | List all historical ledger transactions |
| PATCH | `/v1/inventory/stocks/{stock}` | Replace one stock lot's metadata |

## Testing

The module is heavily tested to ensure reliability in critical stock operations.

```bash
php artisan test --compact app-modules/inventory
```
