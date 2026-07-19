# Validation error mapping

Transaction validation uses package payload keys by default. An integration may provide `errorKeyMap` when its request shape uses different business keys.

```php
$inventory->recordTransaction(
    data: $payload,
    errorKeyMap: [
        'movements.*.item_id' => 'lines.*.product_id',
        'movements.*.location_id' => 'locations.*.id',
    ],
);
```

The same option is available on transaction previews, reversals, and reversal previews. Wildcards are replaced in order and the number of wildcards must remain compatible. A mapping for `movements.*.stock_ids` can cover descendant keys such as `movements.0.stock_ids.2` when the caller does not expose the internal stock index.
