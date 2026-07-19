# Movements

Movements are grouped inside a transaction. The service supports incoming stock, outgoing stock, adjustments, and transfers.

## Incoming stock

An `IN` movement creates a new inventory stock lot. It accepts the quantity, total cost, currency, and optional `stock_metadata` used to populate that new lot. `expiration_date` is required for expirable items and rejected for non-expirable items.

```php
$inventory->recordTransaction([
    'idempotency_key' => 'receipt-1001',
    'transaction_type' => 'in',
    'movements' => [[
        'item_id' => $itemId,
        'location_id' => $locationId,
        'quantity' => 20,
        'unit_code' => 'PCS',
        'total_cost' => 100.01,
        'currency_code' => 'USD',
        'expiration_date' => '2027-01-31',
        'metadata' => ['source' => 'supplier'],
        'stock_metadata' => ['batch' => 'LOT-20'],
    ]],
]);
```

`movement.metadata` describes the receipt event. `stock_metadata` describes the physical lot. They are stored independently. The service converts the input quantity to the item's base unit and derives `unit_cost` and `cost_remainder` from the total cost in minor units.

## Outgoing stock

An `OUT` movement consumes existing stock lots. It does not create stock metadata and does not accept `stock_metadata` from the caller.

```php
$inventory->recordTransaction([
    'idempotency_key' => 'shipment-1001',
    'transaction_type' => 'out',
    'movements' => [[
        'item_id' => $itemId,
        'location_id' => $locationId,
        'quantity' => 3,
        'unit_code' => 'PCS',
        'strategy' => 'fefo',
    ]],
]);
```

The available strategies are `fifo` (default), `fefo`, and `manual`. Manual deduction requires `stock_ids`; the selected lots must provide the complete requested quantity. The movement stores its exact historical `total_cost` and captures the current lot metadata in `stock_metadata_snapshot`.

## Adjustments

An adjustment quantity is the target final quantity at the item/location level.

For a positive adjustment, the service creates a new lot valued at the weighted average cost for the selected currency. Send `currency_code` and optional `stock_metadata`; a supplied `total_cost` is ignored.

```php
$inventory->recordTransaction([
    'idempotency_key' => 'count-increase-1001',
    'transaction_type' => 'adjustment',
    'movements' => [[
        'item_id' => $itemId,
        'location_id' => $locationId,
        'quantity' => 80,
        'unit_code' => 'PCS',
        'currency_code' => 'USD',
        'stock_metadata' => ['reason' => 'count correction'],
    ]],
]);
```

For a negative adjustment, `strategy` and `stock_ids` control which lots are consumed. Cost, currency, and `stock_metadata` do not participate. If manually selected lots do not provide the required reduction, the whole transaction fails without a partial deduction.

## Transfers

A transfer moves stock from `location_id` to `to_location_id`:

```php
$inventory->recordTransaction([
    'idempotency_key' => 'transfer-order-1001',
    'transaction_type' => 'transfer',
    'movements' => [[
        'item_id' => $itemId,
        'location_id' => $sourceId,
        'to_location_id' => $destinationId,
        'quantity' => 60,
        'unit_code' => 'PCS',
        'strategy' => 'manual',
        'stock_ids' => [$stockId],
    ]],
]);
```

Every generated transfer route creates an `OUT` and an `IN` with the same internally generated UUID `link_id`. The destination lot inherits the source lot's metadata, currency, expiration, and exact cost. Multiple routes can be represented in one transaction; each route receives its own `link_id`, making every generated transfer reversible.

The caller does not provide `type`, costs, currency, expiration, `stock_metadata`, or `link_id` for a transfer.
