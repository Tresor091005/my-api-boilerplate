# Models and resources

The module stores inventory records separately from the application's business models. Polymorphic links let the same inventory engine work with products, variants, warehouses, stores, vans, or any other model that implements the relevant contract.

## Inventory item

`InventoryItem` links to the application model through `itemable_type` and `itemable_id`. Important fields are:

- `sku`: optional operational identifier;
- `base_unit_code`: unit used for internal quantities and costing;
- `deduction_strategy`: default FIFO, FEFO, or manual strategy;
- `is_expirable`: whether new inbound lots require an expiration date;
- `stock_tracking_enabled`: whether the item can participate in new stock
  movements.

When no item strategy is configured, expirable items default to FEFO and non-expirable items default to FIFO. FIFO is not valid for expirable items, and FEFO is not valid for non-expirable items. Changing `is_expirable` never rewrites existing stock dates.

If an item becomes expirable while it has older undated lots, those lots remain valid legacy stock. FEFO orders dated lots first and undated lots last; future expiration alerts can report them as having an unknown date.

The application model supplies `getSku()`, `getUnitGroupId()`, `getOrganizationId()`, and `toInventoryItemableSummary()`.

## Inventory location

`InventoryLocation` links to the application model through `external_type` and
`external_id`. Important fields are `is_active`, the polymorphic link, and its
related stocks and movements. An inactive location cannot participate in new
movements, but its historical stock and movement records remain queryable.

The application model supplies `getOrganizationId()` and `toInventoryLocationExternalSummary()`.

## Inventory stock

`InventoryStock` is a physical lot. Important fields are:

- `item_id` and `location_id`;
- `quantity` and `remaining`, stored in the item's base unit;
- `unit_code`, `unit_cost`, and `cost_remainder`;
- `currency_code` and `expiration_date`;
- mutable `metadata`.

Stock metadata is updated independently through the dedicated PATCH endpoint. Quantity and cost are changed only by transactions.

## Inventory movement

`InventoryMovement` is one persisted stock event. It records its `movement_type`, transaction, item, stock, location, quantity, unit, exact `total_cost`, currency, expiration, optional `metadata`, optional transfer `link_id`, and outbound `stock_metadata_snapshot`.

## Inventory transaction

`InventoryTransaction` groups movements and records the `idempotency_key`, `payload_hash`, optional business `reference_type`/`reference_id`, `transaction_type`, optional `metadata`, and optional `reversal_of_transaction_id`.

API resources expose these persisted fields and include the related application model through the configured summary methods when the relation is loaded.
