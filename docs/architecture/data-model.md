# Domain data model

This is the domain-oriented map of the persisted model. The migration files
remain the schema authority; this page explains how the tables work together.

## Identity and authorization

- `iam_users` stores users and uses soft deletion.
- `organization_organizations` stores organizations and uses soft deletion.
- `iam_organization_members` links users to organizations.
- `iam_member_roles` assigns roles to organization members and carries the
  selected role context used in Sanctum token metadata.
- `iam_roles`, `iam_permissions`, and their Spatie pivot tables implement role
  and permission authorization.
- `personal_access_tokens` stores Sanctum tokens, including JSON metadata used
  to restore the selected organization/member-role context.

The selected organization is an authorization boundary, not merely a display
preference. Application services and queries must preserve it explicitly.

## Master data

- `master_currencies` stores currency codes and precision.
- `master_unit_groups` owns coherent unit families.
- `master_units` stores units and their ratio to the group base unit. Tenant
  units use `organization_id`; system units have a null organization.
- `master_labels` stores normalized labels.
- `master_labelables` links labels to arbitrary labelable models by morph type,
  model ID, and label group.

Currency amounts are converted to integer minor units using currency precision.
Unit quantities use ratio-based decimal conversion with BCMath precision.

## Catalog

- `catalog_categories` is hierarchical and soft-deletable.
- `catalog_products` is the product root and may belong to many categories and
  options.
- `catalog_items` stores the organization-scoped operational identity, SKU,
  unit group, active state, and lifecycle for concrete catalog items.
- `catalog_product_variants` belongs to a product and shares its UUID with one
  `catalog_items` row. It stores presentation, options, labels, and the
  calculated name, but no operational SKU or inventory configuration.
- `catalog_options` owns `catalog_option_values`.
- Product/variant option selections use explicit pivot models so product and
  option ownership can be validated.
- Bundles and bundle items are persisted but do not currently have public API
  routes.

Catalog business records are organization-scoped and soft-deletable where the
model supports lifecycle deletion. Nested routes use scoped bindings for
parent/child ownership.

## Inventory ledger

- `inventory_items` and `inventory_locations` are morph-linked adapters around
  host application models. Product inventory items point to CatalogItem, not
  ProductVariant.
- InventoryItem.sku is a denormalized cache. CatalogItem.sku is the source of
  truth.
- CatalogItem.item_type is an explicit discriminator. It currently contains
  `product_variant`, so the referenced business type remains identifiable even
  when the CatalogItem is read without a type-specific relation. The enum maps
  the discriminator to its model class; a batch target loader can be added when
  a cross-type read projection needs eager loading.
- `inventory_items.stock_tracking_enabled` is the inventory-owned switch that
  allows new movements. Catalog variants do not duplicate this setting.
- `inventory_stocks` stores the current lot-level quantity, unit, currency,
  exact cost, expiration, and metadata snapshot.
- `inventory_transactions` is the immutable operation header. It carries the
  transaction type, idempotency key, reference morph, reversal link, and
  metadata snapshot.
- `inventory_movements` stores the individual item/location movements and
  links to stock, units, currencies, and related transfer movements.

Transactions are the source of truth for quantity changes. Stock rows are the
current materialized state used for fast reads. Mutations run inside database
transactions, lock relevant stock rows, and use idempotency/payload hashing to
make retries safe. Reversals create compensating ledger activity rather than
rewriting the original transaction.

## Framework tables

The root database also contains sessions, cache/cache locks, jobs, failed jobs,
job batches, and Telescope entries. These tables belong to infrastructure and
are not business aggregates.

## Soft deletion and historical references

Soft-deleted units and currencies remain resolvable through inventory history
relations with `withTrashed()`. This preserves the meaning of historical
movements even after reference data is retired. New validation generally
rejects soft-deleted references.
