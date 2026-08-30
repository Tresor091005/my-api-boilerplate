# Module map

The application is organized as independent modules under `app-modules/`.
Each module owns its routes, source code, database migrations, resources, and
tests. The root `App` namespace contains framework-level wiring only.

| Module | Responsibility | Public surface | Documentation status |
| --- | --- | --- | --- |
| `iam` | Users, Sanctum tokens, organizations memberships, roles, permissions, authentication context | `/v1/auth/*` | Authentication, membership context, permissions, and policy boundaries are documented. |
| `organization` | Organization lookup and initialization contract used by IAM | No public routes currently | Internal support module; no standalone CRUD API. |
| `catalog` | Categories, products, variants, options, and option values | `/v1/catalog/*` | [Module README](../app-modules/catalog/README.md) and feature blueprints. |
| `master` | Currencies, units, unit conversion, grouped labels, and cached reference data | `/v1/master/*` | Reference-data and cache behavior documented in [caching and consistency](development/caching-and-consistency.md). |
| `inventory` | Generic inventory item/location registration, ledger transactions, stock queries, lots, costs, and reversals | `/v1/inventory/*` | [Module README](../app-modules/inventory/README.md) and module docs. |
| `customer` | Customer identity and organization-scoped polymorphic addresses and contacts | `/v1/customer/*` | Customer module documentation and feature tests. |
| `shared` | Cross-module exceptions, traits, generators, morph-map registry, pagination, handles, and model discovery | Artisan commands and internal contracts | Cross-cutting behavior documented in [application runtime](architecture/application-runtime.md). |

Detailed module notes:

- [IAM](modules/iam.md)
- [Master](modules/master.md)
- [Organization](modules/organization.md)
- [Shared](modules/shared.md)

## Dependency direction

- `shared` provides infrastructure and must not depend on business modules.
- `organization` provides the organization lookup contract consumed by IAM.
- `iam` establishes authentication context and permissions-team context.
- `master` provides currencies, units, conversions, and labels.
- `catalog` may use shared infrastructure and public contracts from other
  modules, but must not import another module's internal implementation.
- `inventory` is package-oriented: the host application supplies the concrete
  item and location models through `HasInventoryItem` and
  `HasInventoryLocation`.
- `customer` may consume the public polymorphic address and contact primitives
  from `master`; it does not own those shared tables.

The architecture tests in `tests/Feature/Architecture` enforce the dependency
boundaries and should be updated when a new module contract is introduced.
