# Getting started

## Domain integration

An item model implements `HasInventoryItem` and uses `InteractsWithInventoryItem`. A location model does the equivalent with `HasInventoryLocation` and `InteractsWithInventoryLocation`.

Items expose their SKU and unit group through `getSku()` and
`getUnitGroupId()`. Locations expose a lightweight external summary.
Tenant-aware host models expose an `organization_id` attribute. The inventory
module remains tenant-agnostic; the host application must enforce access to its
inventory records.

See [Models and resources](models.md) for the persisted fields and the data exposed by API resources.

## Main services

`InventoryInterface` exposes item and location CRUD, transaction recording, previews, and reversals. `InventoryQueryService` provides read-oriented stock, value, movement, and transaction queries.

Read models, stock, valuation, expiring lots, movements, and transactions through `InventoryQueryService`. See [Reading stock and history](reading.md).

```php
$inventory->recordTransaction($payload);
$inventory->previewTransaction($payload);
$inventory->reverseTransaction($transactionId);
$inventory->previewReversal($transactionId);
```

All transaction writes are idempotent when the same `idempotency_key` and equivalent payload are submitted again.

## Units and costs

Transactions may use any unit in the item's unit group. Persistence uses the item's base unit. Monetary input is `total_cost`; the service converts it to minor units and stores the derived `unit_cost` and `cost_remainder` on the stock.

The base unit is indivisible. A transaction quantity may contain decimals only
when conversion produces a whole number of base units. For example, `1.5 m` is
valid when the base unit is `mm`, while `0.0005 m` is rejected because it would
produce `0.5 mm`.

For an `IN` transaction, `currency_code` is required and identifies the
transaction currency. It must be enabled for the organization. When it differs
from the organization's functional currency, a configured directed exchange
rate is required; the service converts the amount at the boundary and stores
the rate and converted amounts in `exchange_metadata`. `OUT`, `ADJUSTMENT`,
`TRANSFER`, and `REVERSAL` do not accept a caller-supplied currency.

See [Movements](movements.md) and [Complete payloads](payloads.md) for operation-specific contracts.
