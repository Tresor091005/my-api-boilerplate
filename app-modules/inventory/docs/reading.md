# Reading stock and history

`InventoryQueryService` provides read paths for current stock, valuation, lot selection, expiring stock, movements, and the transaction ledger. The HTTP controllers expose the same capabilities as API resources and cursor-paginated collections.

## Current stock

- `getItemStock($item)`: total remaining quantity for an item, broken down by location;
- `getLocationStock($location)`: remaining quantity for every item in a location;
- `getItemLocationLots($item, $location, $filters)`: active lots, ordered by FIFO, FEFO, or the requested strategy;
- `listSummary($filters)`: paginated item/location quantity summary;
- `listExpiring($filters)`: paginated lots expiring within a number of days.

```php
$stock = $queries->getItemStock($inventoryItem);
$lots = $queries->getItemLocationLots($inventoryItem, $inventoryLocation, $filters);
```

Lot reads include the stock ID, remaining and original quantity, base-unit cost, cost remainder, currency, expiration, creation date, and metadata. This makes them suitable for a manual picking screen or a stock detail page.

## Quantity and value summary

`listSummary($filters)` returns one row per item/location pair. Each row combines
the current quantity with its total value and functional currency, so consumers
do not need to join separate quantity and value projections.

Values include the cost remainder and are converted from persisted minor units
into the organization's functional currency display amount. Inventory does not
aggregate different currencies together because internal stock costs are
normalized at the inbound boundary.

## History

- `listMovements($filters)`: movements, optionally filtered by item, location, type, date, or business reference;
- `listTransactions($filters)`: transaction ledger;
- `retrieveTransaction($transaction)`: one transaction with its movements, stocks, units, currencies, and locations loaded.

Movement filters support date ranges, `IN`/`OUT` type, and business reference. Transaction filters support IDs, reference type/IDs, transaction type, sorting, and cursors.

## HTTP endpoints

All package routes use the `/v1/inventory` prefix and return resources or cursor-paginated collections:

| Method | Endpoint | Read |
|---|---|---|
| GET | `/items/{item}/locations/{location}/lots` | Active lots for an item/location |
| GET | `/movements` | Movement history, optionally filtered by item or location |
| GET | `/stock/summary` | Global item/location summary |
| GET | `/stock/expiring` | Expiring active lots |
| GET | `/transactions` | List transactions |
| GET | `/transactions/{transaction}` | Retrieve one transaction |

The host application remains responsible for adding its tenant and business authorization boundary around package-generic routes.
