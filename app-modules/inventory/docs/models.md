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

When no item strategy is configured, expirable items default to FEFO and non-expirable items default to FIFO. FIFO is not valid for expirable items, and FEFO is not valid for non-expirable items.

Changing `is_expirable` is strict for active stock. Changing an item from
expirable to non-expirable is rejected when active lots have expiration dates;
changing it from non-expirable to expirable is rejected when active lots do not
have expiration dates. Exhausted lots (`remaining = 0`) do not block the
change. Existing stock dates are never rewritten, and the toggle does not
create an inventory transaction or movement.

The inventoryable application model supplies getSku(), getUnitGroupId(), and
the persisted organization_id attribute. Catalog uses CatalogItem as that
application model for product variant inventory.

## Inventory location

`InventoryLocation` links to the application model through `external_type` and
`external_id`. Important fields are `is_active`, the polymorphic link, and its
related stocks and movements. An inactive location cannot participate in new
movements, but its historical stock and movement records remain queryable.

The application model supplies a persisted `organization_id` attribute.

The item and location integration traits expose only the tenant-scoped
polymorphic InventoryItem or InventoryLocation relation. Stocks, summaries,
and movements remain owned by those Inventory models and are accessed through
them or through the dedicated read endpoints.
Every joined inventory table applies its own qualified
`organization_id` constraint.

## Inventory stock

`InventoryStock` is a physical lot. Important fields are:

- `item_id` and `location_id`;
- `quantity` and `remaining`, stored in the item's base unit;
- `base_unit_code`, `unit_cost`, and `cost_remainder`;
- `currency_code` (the organization's functional currency) and `expiration_date`;
- optional `exchange_metadata`, preserving the conversion snapshot for an
  inbound cost supplied in another enabled currency;
- mutable `metadata`.

Stock metadata is updated independently through the dedicated PATCH endpoint. Quantity and cost are changed only by transactions.

## Inventory movement

`InventoryMovement` is one persisted stock event. It records its `movement_type`, transaction, item, stock, location, quantity, `base_unit_code`, exact `total_cost`, functional currency, expiration, optional `metadata`, optional transfer `link_id`, outbound `stock_metadata_snapshot`, and optional `exchange_metadata` conversion snapshot. Inventory stocks and movements always store quantities in the ratio-1 base unit and costs in the organization's functional currency. The transaction input keeps `unit_code` because callers may submit another unit from the same group.

## Inventory transaction

`InventoryTransaction` groups movements and records the `idempotency_key`, `payload_hash`, optional business `reference_type`/`reference_id`, `transaction_type`, optional `metadata`, and optional `reversal_of_transaction_id`.

API resources expose these persisted fields and include the related application model through the configured summary methods when the relation is loaded.
