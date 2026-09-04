## Current status

### Already usable

- [x] `category`: API CRUD in `catalog`, with filters, hierarchy, business
  assertions, and service tests.
- [x] `product`: API CRUD in `catalog`, with categories, variants, and loading
  of the main relations.
- [x] `variant`: CRUD under `products.variants`; every variant has an
  inventory item, while inventory-owned tracking configuration remains on that
  item.
- [x] `option`: API CRUD in `catalog`, with value management.
- [x] `option value`: API CRUD under `options.values`.
- [x] `unit`: Group and unit reads plus upsert in `master`, with cache and tests.
- [x] `currency`: Listed reads in `master`.
- [x] `label`: Group-based attach, detach, and synchronization; single-group read
  scopes; and tests.
- [x] `inventory item`: Creation, update, deletion, and reads through
  `inventory`.
- [x] `inventory stock`: `in`, `out`, `transfer`, and `adjustment` transactions,
  plus individual lot reads, expiration filtering, movement reads, and
  aggregated quantity/value summaries.
- [x] `permission`: Available for reading the current context through
  `current-permissions`.
- [x] `user`: Available in auth for login, me, logout, password reset, and
  member-role switching.
- [x] `customer`: Identity CRUD API with organization-scoped polymorphic
  addresses and contacts.

### Partially usable

- [~] `organization`: Minimal service and ID lookup are available, but no true
  business CRUD API is exposed.
- [~] `member`: Present in IAM through `organizationMemberships` and
  `MemberRole`, but there is no dedicated service or management API.
- [~] `role`: Present in IAM and used by the auth context, but there is no
  dedicated business CRUD service.

### Not yet industrialized as an autonomous business component

- [ ] `price`: The model and relations exist, but there is no generic service
  or API for assigning prices to any model.
- [ ] `supplier`
- [ ] `procurement`
- [ ] `order`
- [x] Stock-location business concept: Catalog exposes organization-scoped stock
  locations backed by Inventory's `InventoryLocation` adapter.

## Deferred: pricing

Pricing remains handled on the fly for now. A reusable pricing component will
be designed only once the concrete sales and purchasing requirements are
clear.

## TODO: partners and flows

- [ ] Introduce `supplier`.
- [ ] Introduce `procurement`.
- [ ] Introduce `order`.
- [ ] Define business links: `supplier -> procurement`, `customer -> order`.
- [ ] Decide when these flows officially begin affecting Inventory and Pricing.

## TODO: organization currencies and exchange rates

- [ ] Define currency transitions as a migration to a new organization with
  new identifiers, an explicit opening-balance conversion, and closure of the
  previous organization. Do not rewrite historical rows in place.
- [ ] Use conversion at external boundaries: customer payments, refunds,
  supplier invoices, expenses, revenues, imports, and organization
  transitions.
- [ ] Allow foreign-currency payment only when the organization configuration
  permits it. Creating a second organization is reserved for a genuinely
  separate legal, accounting, or operational lifecycle.
- [ ] Add tests for same-currency operations, disabled conversion, fixed-rate
  conversion, inbound and outbound directions, rounding, rate validity, rate
  snapshots, historical reproducibility, and transition opening balances.

## Stock location

- [x] Use the neutral `stock location` business concept in Catalog, backed by
  Inventory's technical `InventoryLocation` adapter.
- [x] Keep the first version flat and organization-scoped; hierarchy can be
  introduced later if warehouse, shelf, van, or shop workflows require it.
- [x] Allow one optional primary address per stock location.
- [x] Add bundle stock operations against stock locations.
- [x] Add draft, complete, and cancel stock transfers between stock locations.

## Already identified in the code

- [ ] `product`: add category filtering.
- [ ] `product variant`: define the sales/order integration that will trigger
  Inventory movements.
- [ ] `unit`: cover safe deletion of groups and units that are already in use.
- [ ] `inventory`: add the business events planned in `plan.md`.
- [ ] `inventory`: decide whether transfer distribution must be specified by the
  user.
- [ ] `iam`: complete what is needed beyond pure authentication if
  `user/member/role/permission` are to become a full application domain.

## Important points

- Global activity logging.

## Deferred technical follow-ups

- [ ] Run the PostgreSQL concurrency scenario for label creation and
  synchronization.
- [ ] Implement backend-controlled field selection for response shapes,
  including resource serialization and required relation dependencies.
- [ ] Audit all migrations together and formalize the tenant-aware foreign-key
  convention: use composite `organization_id` plus identifier constraints
  where cross-tenant references must be impossible, and replace verbose or
  redundant definitions with equivalent project-standard helpers where safe.
- [ ] Define a rule for bounded child collections: request-level `max` limits
  individual payloads, while aggregate-level cardinality limits must also be
  enforced by the owning service (and safely under concurrent writes), taking
  soft-deleted children into account.
- [ ] devrais je laisser les models passer entre differentes packages pour les
  relations eloquent et qu'est ce qui est autorisé à passer
- [ ] prendre le temps de faire les tests d'architecture a partir des rules
