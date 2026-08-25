# API endpoint map

This is the current route inventory. All `/v1/*` routes use the JSON API group
and the global API rate limiter. Business module routes additionally use
`auth.api` unless noted otherwise.

## Authentication

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/auth/register` | public, auth throttle | Create a user. |
| POST | `/v1/auth/login` | public, auth throttle | Issue a Sanctum token. |
| POST | `/v1/auth/forgot-password` | public, auth throttle | Create a password reset token and link. |
| POST | `/v1/auth/reset-password` | public, auth throttle | Consume a reset token and update the password. |
| GET | `/v1/auth/me` | Sanctum + auth context | Return the current user and selected member role. |
| POST | `/v1/auth/logout` | Sanctum + auth context | Revoke the current access token. |
| POST | `/v1/auth/switch-member-role` | `auth.api` | Issue a token for another member role. |
| GET | `/v1/auth/current-permissions` | `auth.api` | Return permissions for the selected organization/role. |

## Catalog

`categories`, `options`, and `products` expose standard API resource actions
(index, store, show, update, destroy). Variants are nested and scoped under
`products/{product}/variants`; option values are nested and scoped under
`options/{option}/values`.

All catalog routes require `auth.api`. Nested scoped binding prevents a child
from being addressed through a parent that does not own it.

## Master data

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/master/currencies` | List currencies. |
| GET | `/v1/master/labels` | List labels for the active organization. |
| PATCH | `/v1/master/labels/{label}` | Update a label value without changing its slug. |
| PUT | `/v1/master/labels/reorder` | Replace the order of all labels for one group. |
| DELETE | `/v1/master/labels/{label}` | Delete an unused label. |
| GET | `/v1/master/labelables/{labelable_type}/{labelable_id}/labels` | List labels attached to a labelable model. |
| GET | `/v1/master/units` | List units, optionally filtered. |
| POST | `/v1/master/units/upsert` | Create or update unit groups and units. |

The labelable labels endpoint resolves `labelable_type` through the registered
morph map and requires the target model's `{morph_alias}.retrieve` permission.
The target must use `InteractsWithLabels` and belong to the active organization.

## Inventory reads and stock metadata

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/v1/inventory/items/{item}/value` | Aggregate item value. |
| GET | `/v1/inventory/items/{item}/locations/{location}/lots` | Read lots for an item/location pair. |
| GET | `/v1/inventory/movements` | Read movements, optionally filtered by item, location, type, date, or business reference. |
| GET | `/v1/inventory/locations/{location}/value` | Aggregate location value. |
| GET | `/v1/inventory/stock/summary` | Read stock summary. |
| GET | `/v1/inventory/stock/expiring` | Read expiring lots. |
| PATCH | `/v1/inventory/stocks/{stock}` | Update stock metadata only. |
| GET | `/v1/inventory/transactions` | List transactions. |
| GET | `/v1/inventory/transactions/{transaction}` | Retrieve a transaction. |

Inventory transaction recording, preview, reversal, item registration, and
location registration are currently service-level operations rather than
public HTTP endpoints. The commented low-stock route is intentionally not
available; its threshold model is still a specification.

## Root routes

- `GET /` renders the welcome page.
- `GET /api/user` is the Laravel starter authenticated-user route.
- `/debug` is Telescope when enabled.
- `/queues` is Horizon when enabled and authorized.

Inventory item and location lifecycle changes are managed by the owning
polymorphic business workflows. Inventory exposes read endpoints for these
records, while item configuration is propagated through those workflows.
- `/docs/api` is the Scramble OpenAPI UI when enabled by the package.
