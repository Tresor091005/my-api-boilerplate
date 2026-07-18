# Inventory Metadata and Transaction Reversal

## Scope

This version supports metadata-aware stock creation and transaction reversal for
`IN` and `OUT` transactions only.

`TRANSFER` reversal is deferred until `link_id` is implemented. `ADJUSTMENT`
reversal is not supported.

## Metadata ownership

Inventory metadata has three separate meanings:

- transaction metadata describes the whole business operation;
- movement metadata describes one persisted movement or event;
- stock metadata describes the current lot represented by an `inventory_stock`.

`movement.metadata` is not an implicit source for `inventory_stocks.metadata`.

## Exact costs

Inbound callers provide `total_cost` in the currency's major units. The service
normalizes it to minor units, derives `unit_cost`, and stores the division
remainder on the stock:

```text
unit_cost      = floor(total_cost / quantity)
cost_remainder = total_cost % quantity
```

The complete remainder is consumed by the first positive deduction from the
lot. Every persisted movement stores the exact allocated `total_cost`. The
same formula remains valid for every deduction:

```text
deducted_total_cost = quantity * unit_cost + allocated_cost_remainder
```

After the first deduction, `allocated_cost_remainder` is zero. Transfers and
reversals reuse the persisted minor-unit totals directly.

### Inbound stock metadata

`stock_metadata` is an optional input field for movements that create a stock:

- a regular `IN` transaction;
- a positive `ADJUSTMENT`.

When supplied, it is copied to the newly created stock. When omitted, the new
stock metadata is `null`. It is never merged with `movement.metadata`.

`stock_metadata` is not accepted for `OUT` movements. On an adjustment, the
service uses it only if the adjustment increases stock and ignores it when the
adjustment decreases stock. A transfer does not accept destination
`stock_metadata` from the caller: it copies the source stock metadata to the
destination stock.

The transfer's inbound `movement.metadata` remains attached to the persisted
inbound movement and is not copied into the destination stock metadata.

### Outbound stock snapshot

An `OUT` movement does not accept `stock_metadata` from the caller. During
deduction, the service copies the source stock metadata into the persisted
`stock_metadata_snapshot` column on the `inventory_movements` row before
changing the stock's `remaining` value.

The snapshot records the stock state at the time of the outbound event. It is
not changed when the current stock metadata is later edited through the stock
metadata endpoint.

## Stock metadata endpoint

The endpoint is:

```http
PATCH /v1/inventory/stocks/{stock}
```

The body replaces the complete metadata JSON:

```json
{
  "metadata": {
    "batch": "LOT-1",
    "status": "quarantine"
  }
}
```

`metadata: null` clears the metadata. The endpoint cannot change quantity,
remaining quantity, cost, currency, expiration, item, location, or unit.

No application-level audit or logging is added by the inventory module.

## Reversal contract

```php
$inventory->reverseTransaction(
    originalTransactionId: $transaction->id,
    metadata: ['reason' => 'order_cancelled'],
);
```

The reversal:

- is a normal transaction with its own ID;
- uses `idempotency_key = "{$originalTransactionId}:reverse"`;
- stores `reversal_of_transaction_id`;
- can exist only once for an original transaction;
- cannot itself be reversed;
- uses the persisted movements that were actually executed;
- is atomic and idempotent;
- does not copy the original transaction metadata automatically.

The reversal transaction metadata is exactly the metadata supplied by the
caller. The original transaction remains available through
`reversalOf()`.

### Original `IN` to reversal `OUT`

For every persisted original inbound movement, create a manual outbound
movement that:

- uses exactly the original `stock_id`;
- uses the persisted quantity and base unit;
- does not recalculate cost from current stock;
- does not provide `stock_metadata`.

The normal outbound pipeline decreases `remaining`. It never directly restores
or rewrites the original stock row.

### Original `OUT` to reversal `IN`

For every persisted original outbound movement, create an inbound movement that
reuses:

- the persisted quantity;
- the persisted minor-unit cost;
- the persisted currency;
- the persisted expiration;
- the persisted outbound movement metadata as movement metadata;
- `stock_metadata_snapshot` as the new stock metadata.

The normal inbound pipeline creates a new stock. The original stock remains
historically decreased, and its `remaining` column is never restored directly.

The current stock metadata is not consulted during reversal because it may have
changed, been depleted, or been soft-deleted after the original outbound event.

## Validation and persistence constraints

- `stock_metadata` is nullable and is validated as an array when present;
- `stock_metadata_snapshot` is nullable at the database level for legacy rows;
- an `IN` movement must never persist a non-null snapshot;
- new `OUT` movements always persist the source stock snapshot;
- the reversal uses the normal `recordTransaction` validation and processing
  pipeline;
- a reversal fails atomically when its persisted source data is inconsistent,
  its required stock is unavailable, or the normal pipeline rejects the inverse.

## Idempotency and failures

The first reversal call creates the counter-entry. A retry with the same
deterministic key and payload returns the existing reversal. A retry with the
same key and different metadata raises the existing idempotency exception.

The unique partial index on `reversal_of_transaction_id` protects the one-to-one
relationship, while the original transaction row is locked during reversal
construction.

## Deferred transfer reversal design

Transfer reversal is deferred until `link_id` is added to movements. Each
transfer lane will then preserve one source `OUT` and one destination `IN`, and
the reversal can invert each lane without mixing destinations. Transfers
without an unambiguous lane mapping remain readable but are not reversible.
