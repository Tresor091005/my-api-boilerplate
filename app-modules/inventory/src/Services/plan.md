TODO:

- [x] 1. Add transaction idempotency with:
  - `idempotency_key` validated as a required string with a minimum length of 3.
  - `payload_hash` stored to detect reuse of the same idempotency key with a different payload.
  - No uniqueness constraint on `reference_type` + `reference_id`.
- 2. Add transaction reversal with `reversal_of_transaction_id`:
  - Expose `$inventory->reverseTransaction(originalTransactionId: $transaction->id, metadata: ['reason' => 'order_cancelled'])`.
  - Allow at most one reversal for a given original transaction.
- 3. dispatchEvents(): LowStockDetected, InventoryTransactionRecorded, StockDecreased, StockIncreased, etc.
- 4. Let the caller control transfer stock distribution through a `link_id`, for example.
- 5. Add a non-persistent transaction preview:
  - Expose `$inventory->previewTransaction($data)` for stock availability and projected impact checks.
  - Do not create transactions, movements, stocks, or dispatch events during a preview.
- [x] 6. Preserve exact lot costs with a cost remainder:
  - Accept `total_cost` as the public inbound cost.
  - Persist normalized minor-unit `total_cost` on movements and `cost_remainder` on stocks.
  - Support `quantity * unit_cost + cost_remainder = total_cost`.
  - Consume the complete remainder during the first positive deduction from a lot.
  - Carry the exact allocated cost through transfers and reversal transactions.

Positive adjustments must not silently create zero-cost stock when no usable cost source exists; this follows the `total_cost` implementation.

une exception est interessante quand elle contient plusieur static comme tu l'as fait pour reversal

shape des differents type de fichier dans le code et normalisation

supprimer le contrat de fichier transactionnel ou pas, pas important
