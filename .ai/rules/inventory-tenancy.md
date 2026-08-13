---
paths:
  - app-modules/inventory/**
---

# Inventory Tenancy

This file documents inventory-specific tenancy decisions. For the general query,
soft-delete, indexing, batching, and persistence policy, read
`.ai/rules/persistence-tenancy.md` first.

- Inventory is tenant-scoped. Items, locations, stocks, transactions, and
  movements carry `organization_id`.
- Resolve the active organization through the established inventory context,
  currently provided by `ResolvesInventoryOrganization`. Fail explicitly when
  that context is absent; do not silently use a request value or a nullable
  fallback.
- When a service receives an already resolved inventory model, verify that its
  `organization_id` matches the active organization before reading related
  state or mutating it. A model identifier alone is not sufficient evidence of
  ownership.
- Preserve the same `organization_id` across every inventory aggregate and
  relationship: transaction, movement, stock, item, and location. Reject
  cross-organization combinations before persistence.
- Keep inventory-specific ownership and consistency checks inside inventory
  services, assertions, and validators. Another module must not be responsible
  for enforcing inventory tenancy.
- HTTP authorization remains governed by `.ai/rules/http-api.md`; organization
  scope and UUID identifiers do not replace Controller or Policy decisions.

## References

- `.ai/rules/persistence-tenancy.md` — shared query and persistence boundaries.
- `.ai/rules/domain-services-data.md` — inventory service orchestration and
  typed service data.
- `app-modules/inventory/src/Traits/ResolvesInventoryOrganization.php` — active
  organization resolution.
- `app-modules/inventory/src/Exceptions/OrganizationScopeException.php` —
  explicit organization mismatch failure.
