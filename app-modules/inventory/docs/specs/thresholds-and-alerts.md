# Inventory Thresholds and Alerts Specification

Goal: introduce a straightforward threshold and alert system in the Inventory
module while remaining package-friendly: configurable, loosely coupled, with
few false positives and debounced alerts.

Date: 2026-04-10

## 1. Principles

- Thresholds are defined by scope: `global`, `item` (across locations), or
  `item_location`.
- Alerts must be idempotent and debounced: create an alert only when a breach
  is crossed and resolve it when the condition is no longer true.
- Existing read endpoints remain the source of truth for stock. Alerts must not
  force more data to be loaded than necessary.
- Configuration is progressive: a project may start with only `min/max` and
  later add expiration, movement, or value thresholds.
- Do not store thresholds in `inventory_items`, `inventory_locations`, or
  `inventory_stocks`; create dedicated rule and alert tables.

## 2. Scopes

`global`

- Applies to all active items.

`item`

- Applies to one `inventory_item` across all its locations.

`item_location`

- Applies to the `(inventory_item, inventory_location)` pair.

Rule resolution:

- Priority: `item_location` > `item` > `global`.
- A rule may define only part of the fields, for example only
  `expiring_soon_days`.

## 3. Alert types (v1)

### 3.1 Quantity (`remaining`)

- `under_min_remaining`
  - Condition: `total_remaining < min_remaining`.
- `over_max_remaining`
  - Condition: `total_remaining > max_remaining`.
- `stockout`
  - Condition: `total_remaining = 0`.
  - Note: It could be an alias for `under_min_remaining` when
    `min_remaining` is 1, but it remains explicit because it is a frequent
    alert.

### 3.2 Expiration

- `expiring_soon`
  - Condition: at least one active lot (`remaining > 0`) expires within
    `expiring_soon_days`.
- `expired`
  - Condition: at least one active lot has already expired.

### 3.3 Movement

- `no_movement_since`
  - Condition: no movement for `no_movement_days` while
    `total_remaining > 0`.
- `too_many_movements`
  - Goal: detect abnormal activity, such as an integration bug, duplicate
    submission, or fraud.
  - Simple condition: within a rolling `movement_window_days` window,
    `movement_count` or `moved_quantity` exceeds a threshold.
  - Example rules:
    - `movement_count > max_movements_per_window`
    - `sum(abs(quantity_base_unit)) > max_moved_qty_per_window`

## 4. Financial value (v2)

The lots already have `unit_cost` and `currency_code`, but the definition must
be clarified.

Proposals:

- `total_value` per scope = `SUM(remaining * unit_cost)` in `currency_code`.
- Constraints:
  - If several currencies exist in the scope, do not calculate, or require an
    expected currency through the rule.
- Alerts:
  - `under_min_value`, `over_max_value`.

## 5. Data model

### 5.1 `inventory_threshold_rules`

Purpose: define scope-based rules that can be enabled, disabled, and evolved
without changing core tables.

Proposed fields:

- `id` UUID (PK).
- `scope` enum: `global`, `item`, `item_location`.
- `item_id` UUID nullable FK → `inventory_items.id`.
- `location_id` UUID nullable FK → `inventory_locations.id`.
- `stock_tracking_enabled` boolean, defaulting to true on inventory items;
  threshold evaluation must ignore items that do not participate in stock
  tracking.
- Quantity:
  - `min_remaining` bigint nullable.
  - `max_remaining` bigint nullable.
- Expiration:
  - `expiring_soon_days` integer nullable.
- Movement:
  - `no_movement_days` integer nullable.
  - `movement_window_days` integer nullable.
  - `max_movements_per_window` integer nullable.
  - `max_moved_qty_per_window` bigint nullable.
- Value (v2):
  - `min_value` bigint nullable.
  - `max_value` bigint nullable.
  - `currency_code` char(3) nullable.
- `metadata` jsonb nullable.
- Timestamps.
- Soft deletes, optional but useful for preserving configuration history.

Constraints:

- Scope check:
  - `global` → `item_id IS NULL AND location_id IS NULL`.
  - `item` → `item_id IS NOT NULL AND location_id IS NULL`.
  - `item_location` → `item_id IS NOT NULL AND location_id IS NOT NULL`.
- Uniqueness, using partial unique indexes with `deleted_at IS NULL` when soft
  deletes are enabled:
  - one `global` rule;
  - one `item` rule per `item_id`;
  - one `item_location` rule per `(item_id, location_id)`.

### 5.2 `inventory_alerts`

Purpose: store alert state (`open`/`resolved`), a snapshot, and debounce data.

Proposed fields:

- `id` UUID (PK).
- `rule_id` UUID FK → `inventory_threshold_rules.id`.
- `type` enum:
  - `under_min_remaining`, `over_max_remaining`, `stockout`;
  - `expiring_soon`, `expired`;
  - `no_movement_since`, `too_many_movements`;
  - (v2) `under_min_value`, `over_max_value`.
- `status` enum: `open`, `resolved`.
- `opened_at` timestamp.
- `resolved_at` timestamp nullable.
- `snapshot` jsonb, for example totals, IDs, counts, last movement time,
  expiring count, and sample lot IDs.
- `last_evaluated_at` timestamp nullable, useful for scheduled evaluation.
- Timestamps.

Idempotency rule:

- Logical key `(rule_id, type, status=open)` is unique through a partial unique
  index to prevent duplicates.

## 6. Rule evaluation

### 6.1 Two channels

On write, synchronously or through a queue:

- When a transaction is recorded, reevaluate only:
  - affected `item` scopes;
  - affected `item_location` scopes.
- Targets:
  - `under_min_remaining`, `over_max_remaining`, `stockout`;
  - `too_many_movements` may also be evaluated here if the query remains
    reasonably small.

Scheduler, every few hours or once per day:

- Evaluate:
  - `expiring_soon`, `expired`;
  - `no_movement_since`;
  - `too_many_movements` when avoiding on-write cost is preferable.

### 6.2 Basic calculations by scope

`total_remaining`

- item: `SUM(remaining)` on `inventory_stocks` or through an aggregate relation.
- item_location: `SUM(remaining)` filtered by `(item_id, location_id)`.
- global: either per item, which is less noisy, or all items. Recommendation:
  `global` is a fallback rule, but evaluation remains per item or
  item-location.

Expiration:

- `expiring_soon`: active lots with `expiration_date <= now +
  expiring_soon_days`.
- `expired`: active lots with `expiration_date < now`.

Movement:

- `last_movement_at`: `MAX(inventory_movements.created_at)` per scope.
- `movement_count` and `moved_quantity` over a window:
  - Window: `created_at >= now - movement_window_days`.
  - Quantity: ideally in base units; otherwise in `unit_code` (v1: count only,
    v1.1: base-unit quantity).

Debounce:

- Creation: if a breach is detected and no open alert exists.
- Resolution: if there is no breach and an open alert exists.
- Optional cooldown per type, such as 24 hours, for a “still low” event; not
  required in v1.

## 7. Events

Proposed events:

- `InventoryAlertOpened`.
- `InventoryAlertResolved`.

Minimal payload:

- `alert_id`;
- `type`;
- `scope` + `item_id` + `location_id`;
- `snapshot`.

The system may also emit `InventoryTransactionRecorded`, when not already
available, and let a listener start evaluation.

## 8. API (read and administration)

Read:

- `GET /v1/inventory/alerts` (cursor pagination).
  - Filters: `status`, `type`, `item_id`, `location_id`, `scope`.
- `GET /v1/inventory/alerts/{alert}`.

Threshold administration:

- `GET /v1/inventory/threshold-rules`.
- `GET /v1/inventory/threshold-rules/{rule}`.
- `POST /v1/inventory/threshold-rules`.
- `PATCH /v1/inventory/threshold-rules/{rule}`.
- `DELETE /v1/inventory/threshold-rules/{rule}`.

In package mode, an admin UI is optional, but the API makes integration much
easier.

## 9. Expected indexes

Stocks (PostgreSQL partial indexes):

- `(item_id, location_id)` WHERE `deleted_at IS NULL AND remaining > 0`.
- `(location_id, item_id)` WHERE `deleted_at IS NULL AND remaining > 0`.
- `(expiration_date)` WHERE `deleted_at IS NULL AND remaining > 0 AND
  expiration_date IS NOT NULL`.

Movements, if movement evaluation is implemented:

- `(item_id, created_at)`.
- `(item_id, location_id, created_at)`.
- Optional `(transaction_id)`, which already exists.

Threshold rules:

- Indexes on `scope`, `item_id`, `location_id`, and `is_active`.

Alerts:

- Indexes on `status`, `type`, `opened_at`, and `item_id/location_id` when
  denormalized into snapshot or columns.

## 10. Open decisions

- Global scope: evaluate per item or as the total of all items.
  - Recommendation: evaluate per item because it is less noisy and more
    actionable.
- Movement thresholds: v1 count only, or v1.1 quantity in base units, which
  requires conversion.
- Value: require one currency per rule, or refuse calculation when the scope
  contains multiple currencies.
