# Stock metadata

Inventory metadata has separate ownership:

- `transaction.metadata` describes the business operation as a whole;
- `movement.metadata` describes one persisted event in that operation;
- `stock_metadata` describes the physical lot created by an inbound operation;
- `stock_metadata_snapshot` records the lot metadata at the moment an outbound movement consumes it.

`movement.metadata` is never implicitly merged into stock metadata.

## Creation rules

- `IN`: optional `stock_metadata` populates the new stock lot.
- Positive adjustment: optional `stock_metadata` populates the new adjustment lot.
- Negative adjustment: no lot is created, so stock metadata has no effect.
- `OUT`: the caller does not provide stock metadata; the service snapshots the current lot metadata.
- Transfer: destination lots inherit the source lot metadata.
- Reversal of `OUT`: the new inbound lot uses the outbound `stock_metadata_snapshot`.

## Updating a lot

Stock metadata can be replaced independently of transactions:

```http
PATCH /v1/inventory/stocks/{stock}
Content-Type: application/json

{"metadata":{"batch":"B-123","status":"quarantine"}}
```

The endpoint changes only `metadata`. It does not change quantity, cost, currency, expiration, item, location, or unit. Sending `metadata: null` clears it.
