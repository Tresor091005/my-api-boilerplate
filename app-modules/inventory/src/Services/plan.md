TODO:

- 2. Add transaction reversal with `reversal_of_transaction_id`:
  - Expose `$inventory->reverseTransaction(originalTransactionId: $transaction->id, metadata: ['reason' => 'order_cancelled'])`.
  - Allow at most one reversal for a given original transaction.
- 3. dispatchEvents(): LowStockDetected, InventoryTransactionRecorded, StockDecreased, StockIncreased, etc.
- 4. Let the caller control transfer stock distribution through a `link_id`, for example.
- 5. Add a non-persistent transaction preview:
  - Expose `$inventory->previewTransaction($data)` for stock availability and projected impact checks.
  - Do not create transactions, movements, stocks, or dispatch events during a preview.
une exception est interessante quand elle contient plusieur static comme tu l'as fait pour reversal

shape des differents type de fichier dans le code et normalisation

supprimer le contrat de fichier transactionnel ou pas, pas important
