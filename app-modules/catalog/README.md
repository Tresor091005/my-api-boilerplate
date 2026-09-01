# Catalog Module

## Domain exceptions

Model-specific invariants are grouped by model: `CategoryException`,
`OptionException`, `OptionValueException`, and `ProductVariantException`. Use
their named static methods from assertions.

See the [general business exception convention](../../docs/architecture/coding-rules/exceptions.md).

## Catalog item identity

CatalogItem is the internal operational identity for a concrete catalog item.
Product variants keep their presentation data, while the variant UUID is also
the CatalogItem UUID. SKU, unit group, and active state belong to CatalogItem
and remain flattened in the existing variant API response.
CatalogItem.item_type identifies the referenced business type and currently
uses `catalog_product_variant` and `catalog_bundle`. A Bundle shares its UUID with CatalogItem,
uses the built-in `bundle` unit group, and is stockable through its own
`InventoryItem`. Detailed bundle stock operations are not implemented yet.
Bundle components reference CatalogItems and currently accept product variants
only.
BundleItem keeps item_type as a string discriminator and does not define a
type-specific target relation. A batch target loader can resolve components
when a read projection needs their models.
BundleItem quantities are stored in the referenced item's ratio-1 base unit.
Bundle item API payloads use `unit_code`; the service converts the submitted
quantity to the base unit for persistence and stores that code internally as
`display_unit_code`. Resources convert the persisted quantity back to
`unit_code` for the API response.
CatalogItem intentionally has no type-specific reverse relation; callers use
the enum mapping when they need to resolve its target model.

## Product variant labels

`ProductVariant` uses the Master `InteractsWithLabels` trait. Variant creation accepts
an optional `labels` object organized by group:

```json
{
  "labels": {
    "status": ["active"],
    "channel": ["online", "store"]
  }
}
```

The same validation schema is reused for direct variant batches and variants
nested in product creation. Labels are attached inside the existing variant
creation transaction. The `ProductVariantResource` exposes labels only when the
relation has already been loaded; Catalog does not eager-load the relation by
default.

During a variant update, only submitted label groups are synchronized. Omitted
groups remain unchanged, while an explicitly empty group removes all labels of
that group.

## Tests

This section documents the testing strategy for `catalog`, which can also be
reused by other modules.

### 1. Required separation

- `app-modules/catalog/tests/*`:
  - Module-local business tests.
  - Targets `Service`, `Data`, `Assertions`, and persistence.
  - Does not bootstrap the full IAM stack (`User`, `Role`, `Permission`,
    `Organization`).
- `tests/Feature/Integration/*`:
  - Cross-module tests for authentication, policies, gates, and middleware.
  - Covers HTTP tenancy and permission matrices.

Simple rule:

- “What does the business logic do?” → module-local.
- “Who is allowed to do it?” → integration.

### 2. Module-local pattern (catalog)

In module-local tests:

- Initialize the tenant context through the trait:
  - [InteractsWithCatalogTenantContext.php](./tests/Concerns/InteractsWithCatalogTenantContext.php)
- Call services directly:
  - `CategoryService`, `OptionService`, `OptionValueService`, `ProductService`,
    `ProductVariantService`.
- Validate payloads through the Form Request:
  - validate payloads with Form Request rules and test the
    `XxxData::fromArray(...)` mapping separately.
- For collections and resources:
  - use `->response()->getData(true)` and assert the `data` payload.

### 3. Integration pattern (catalog)

HTTP tenancy and authorization tests live in:

- [CatalogTenancyIntegrationTest.php](./../../tests/Feature/Integration/CatalogTenancyIntegrationTest.php)
- [CatalogAuthorizationIntegrationTest.php](./../../tests/Feature/Integration/CatalogAuthorizationIntegrationTest.php)

They cover:

- List: only the current organization.
- Show/update/delete: allowed for the current organization and denied for
  another organization.
- Create: `organization_id` is assigned automatically to the current tenant.
- Permissions: `403` when permissions are removed.

### 4. Factories and foreign keys

Catalog factories do not import Organization models, but they respect foreign
keys:

- They insert a minimal row in `organization_organizations` when necessary.
- They reuse `currentOrganizationId()` when available.

The goal is to remain compatible with `ModularDependencyTest` while keeping
tests stable.

### 5. Useful commands

```bash
docker compose exec app php artisan test --compact app-modules/catalog/tests
docker compose exec app php artisan test --compact tests/Feature/Integration/CatalogTenancyIntegrationTest.php
docker compose exec app php artisan test --compact tests/Feature/Integration/CatalogAuthorizationIntegrationTest.php
docker compose exec app php artisan test --compact tests/Feature/Architecture/ModularDependencyTest.php
```

### 6. Checklist after adding code

1. Add or adapt the business test in `app-modules/catalog/tests`.
2. If authentication or policy behavior is affected, add an integration test
   in `tests/Feature/Integration`.
3. Check that no forbidden cross-module import was introduced.
4. Run `ModularDependencyTest` before merging.
