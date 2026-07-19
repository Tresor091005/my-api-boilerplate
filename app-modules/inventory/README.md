# Inventory Module

The Inventory Module provides a reusable, location-aware inventory ledger for Laravel applications. It tracks items across warehouses and other locations, preserves lot-level quantities and exact costs, supports FIFO/FEFO/manual deductions, and reverses transactions without rewriting history.

## What it provides

- Immutable transactions and movements for in, out, adjustments, and transfers.
- Lot-level stock with expiration, currency, metadata, and exact minor-unit costs.
- Base-unit conversion through the Master module.
- FIFO, FEFO, or manual lot selection for deductions.
- Metadata updates through a dedicated stock endpoint.
- Idempotent reversals for in, out, and transfers.
- Rollback-only previews that run the real validation and calculation pipeline.
- Query services and API resources for stock, value, movements, and transactions.

## Bring your own models

The module does not force a product, warehouse, or catalog model. Any application model can become an inventory item or location by implementing the corresponding contract. A `Product`, `ProductVariant`, `Warehouse`, `Store`, or `ServiceVan` can therefore be used directly as the business object behind inventory records.

Inventory items expose the important operational fields `sku`, `base_unit_code`, `deduction_strategy`, and `is_active`. Inventory locations expose their polymorphic `external_type`, `external_id`, and `is_active`. The persisted records keep the link to the application model while inventory stocks, movements, and transactions remain independent ledger data.

Inventoryable domain models implement the relevant contract and use its trait:

```php
class Product extends Model implements HasInventoryItem
{
    use InteractsWithInventoryItem;

    public function getSku(): string { return $this->sku; }
    public function getUnitGroupId(): string { return $this->unit_group_id; }
    public function getOrganizationId(): string { return $this->organization_id; }
}
```

The optional reference resolver lets transaction movements receive those models directly instead of pre-resolved inventory IDs:

```php
// config/inventory.php
'enable_model_reference_preprocessing' => true,

$inventory->recordTransaction([
    'transaction_type' => 'in',
    'movements' => [[
        'item' => $product,
        'location' => $warehouse,
        'quantity' => 10,
        'unit_code' => 'PCS',
        'total_cost' => 1500.00,
        'currency_code' => 'USD',
    ]],
]);
```

The resolver ensures the inventory records exist, replaces the model references with internal IDs, and then runs the normal validation and transaction pipeline.

## Batch registration

Items and locations can be created one at a time or in batches. A batch may contain different model classes; the resolver groups them by morph type, performs the necessary lookups and inserts, and returns the resulting inventory records as a collection.

```php
$inventory->createManyItems(collect([$product, $productVariant, $servicePart]));
$inventory->createManyLocations(collect([$warehouse, $store, $serviceVan]));
```

## Configuration

The module currently keeps configuration intentionally small:

- `default_strategy`: fallback deduction strategy used only when neither the movement nor the item provides one;
- `enable_model_reference_preprocessing`: enables direct model references in transaction movements; disabled by default for explicit ID-only payloads.

Record a receipt with optional stock metadata:

```php
$transaction = $inventory->recordTransaction([
    'idempotency_key' => 'purchase-order-'.$order->id,
    'reference_type' => 'purchase_order',
    'reference_id' => $order->id,
    'transaction_type' => 'in',
    'movements' => [[
        'item_id' => $itemId,
        'location_id' => $warehouseId,
        'quantity' => 10,
        'unit_code' => 'PCS',
        'total_cost' => 1500.00,
        'currency_code' => 'USD',
        'stock_metadata' => ['batch' => 'B-123'],
    ]],
]);
```

Then deduct stock using the default strategy or an explicit one:

```php
$inventory->recordTransaction([
    'idempotency_key' => 'shipment-'.$shipment->id,
    'transaction_type' => 'out',
    'movements' => [[
        'item_id' => $itemId,
        'location_id' => $warehouseId,
        'quantity' => 3,
        'unit_code' => 'PCS',
        'strategy' => 'fifo',
    ]],
]);
```

## Documentation

- [Getting started](docs/getting-started.md)
- [Movements: incoming, outgoing, adjustments, and transfers](docs/movements.md)
- [Reversals](docs/reversals.md)
- [Stock metadata](docs/stock-metadata.md)
- [CRUD operations](docs/crud.md)
- [Preview operations](docs/preview.md)
- [Reading stock and history](docs/reading.md)
- [Validation error mapping](docs/error-mapping.md)
- [Complete payload reference](docs/payloads.md)
- [Models and resources](docs/models.md)

## Package boundary

The module owns inventory rules and persistence. The host application owns authentication, permissions, tenant access, and the relationship between inventory records and its business users. The package-generic routes should not be exposed directly as tenant-safe application routes without that host-level access boundary.

## Tests

```bash
php artisan test --compact app-modules/inventory
```
