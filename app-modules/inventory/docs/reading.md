# Reading stock and history

`InventoryQueryService` provides read paths for current stock, valuation, lot selection, expiring stock, movements, and the transaction ledger. The HTTP controllers expose the same capabilities as API resources and cursor-paginated collections.

## Current stock

- `getItemStock($item)`: total remaining quantity for an item, broken down by location;
- `getLocationStock($location)`: remaining quantity for every item in a location;
- `listSummary($filters)`: paginated item/location quantity summary;
- `listExpiring($filters)`: paginated lots expiring within a number of days.

```php
$stock = $queries->getItemStock($inventoryItem);
```

Lot reads include the stock ID, remaining and original quantity, base-unit cost, cost remainder, currency, expiration, creation date, and metadata. `GET /stocks` with one `item_id`, a `location_id` and `sort_by=deduction` lists the open lots in the order an outgoing movement takes them (the `strategy` asked, else the item's, else FEFO for an item that expires and FIFO otherwise): what a manual picking screen needs to fill `stock_ids`.

## Quantity and value summary

`listSummary($filters)` returns one row per item/location pair. Each row combines
the current quantity with its total value and functional currency, so consumers
do not need to join separate quantity and value projections.

Values include the cost remainder and are converted from persisted minor units
into the organization's functional currency display amount. Inventory does not
aggregate different currencies together because internal stock costs are
normalized at the inbound boundary.

## History

- `paginateMovements($filters)`: movements, optionally filtered by item, location, type, date, business reference, transaction, or stock lot;
- `paginateTransactions($filters)`: transaction ledger;
- `retrieveTransaction($transaction)`: one transaction of the active organization, without relations; the HTTP response loads the includes the client selects.

Movement filters support date ranges, `IN`/`OUT` type, business reference, transaction (`transaction_id`), and stock lot (`stock_id`). An included `movements` list renders at most 100 movements per stock or transaction; these two filters page through all of them. Transaction filters support IDs, reference type/IDs, transaction type, sorting, and cursors.

## HTTP endpoints

All package routes use the `/v1/inventory` prefix. Stocks, movements and transactions answer through [response selection](../../../docs/api/response-selection.md) (`?fields=`, `?include=`); the summary read returns computed rows. The two stock reads give the state of the stock: lots, and totals per item and location:

| Method | Endpoint | Read |
|---|---|---|
| GET | `/movements` | Movement history, optionally filtered by item, location, transaction or stock lot |
| GET | `/stocks/summary` | Global item/location summary |
| GET | `/stocks` | Lots; `has_remaining`, `expiring_within_days`, and `sort_by=deduction` for one item |
| GET | `/transactions` | List transactions |
| GET | `/transactions/{transaction}` | Retrieve one transaction |

The host application remains responsible for adding its tenant and business authorization boundary around package-generic routes.
