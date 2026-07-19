# Reversals

A reversal is a new ledger transaction. It never rewrites the original transaction and never directly restores the original stock row's `remaining` value.

```php
$reversal = $inventory->reverseTransaction(
    originalTransactionId: $transaction->id,
    metadata: ['reason' => 'order_cancelled'],
);
```

Supported transformations:

- Original `IN` becomes a manual `OUT` against the original stock lot.
- Original `OUT` becomes a new `IN` using the movement's persisted quantity, exact total cost, currency, expiration, and `stock_metadata_snapshot`.
- Original `TRANSFER` reverses every linked route independently: destination `OUT`, then source `IN`.
- `ADJUSTMENT` reversal is not supported.

Reversal-generated inbound movements reuse the original persisted expiration date. A legacy movement with no date keeps that absence so the ledger is not rewritten.

The original transaction metadata is not copied automatically. Reversal metadata belongs to the new reversal transaction and is supplied by the caller. Movement metadata is copied only where the reversal needs the original event context; stock metadata comes from the original inbound lot or the outbound snapshot.

The reversal uses `{$originalTransactionId}:reverse` as its deterministic idempotency key. Repeating the same operation returns the existing reversal. Reusing that key with a different payload fails. A reversal also fails when the stock required for the inverse operation has already been consumed or is otherwise insufficient.
