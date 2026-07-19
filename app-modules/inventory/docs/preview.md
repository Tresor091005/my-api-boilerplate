# Preview operations

Preview methods execute the same validation, deduction, costing, transfer, and reversal pipeline as a real write, but roll the database transaction back before returning.

```php
$inventory->previewTransaction($payload);
$inventory->previewReversal($transaction->id, ['reason' => 'order_cancelled']);
```

Both methods return `void` on success. They propagate the same validation and business exceptions as the corresponding write operation on failure. A successful preview leaves no transaction, movement, stock, stock change, or model event behind.
