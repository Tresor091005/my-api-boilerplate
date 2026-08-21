# Catalog Module

## Domain exceptions

Model-specific invariants are grouped by model: `CategoryException`,
`OptionException`, `OptionValueException`, and `ProductVariantException`. Use
their named static methods from assertions.

See the [general business exception convention](../../docs/architecture/coding-rules/exceptions.md).

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
