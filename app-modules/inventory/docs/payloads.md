# Payload reference

## Common transaction shape

```php
[
    'idempotency_key' => 'unique-operation-key',
    'reference_type' => 'purchase_order', // optional
    'reference_id' => $referenceId,       // optional
    'transaction_type' => 'in|out|adjustment|transfer',
    'metadata' => ['source' => 'erp'],    // optional transaction metadata
    'movements' => [/* movement payloads */],
]
```

The complete operation examples live in [Movements](movements.md). This page records the field contract without duplicating those examples.

## Movement field rules

| Field | `IN` | `OUT` | Adjustment | Transfer |
|---|---|---|---|---|
| `item_id`, `location_id`, `quantity`, `unit_code` | required | required | required | required |
| `to_location_id` | forbidden | forbidden | forbidden | required |
| `total_cost`, `currency_code` | cost and currency required | forbidden | currency only for an increase; cost ignored | forbidden |
| `expiration_date` | optional | forbidden | ignored for decrease | forbidden |
| `stock_metadata` | initializes the new lot | forbidden | initializes an increase lot; ignored for decrease | forbidden; copied from source lot |
| `strategy`, `stock_ids` | forbidden | optional / manual selection | optional / manual selection for decrease | optional / manual selection |
| `link_id` | caller must not send | caller must not send | caller must not send | generated internally |

For `OUT`, total cost, currency, expiration, and stock metadata are derived from the consumed lots rather than supplied by the caller. For a negative adjustment, `quantity` remains the target final quantity.

## Reversal and preview

```php
$inventory->reverseTransaction($originalId, ['reason' => 'cancelled']);
$inventory->previewTransaction($payload, $errorKeyMap);
$inventory->previewReversal($originalId, ['reason' => 'cancelled'], $errorKeyMap);
```

See [Reversals](reversals.md), [Preview operations](preview.md), and [Validation error mapping](error-mapping.md) for the complete behavior.
