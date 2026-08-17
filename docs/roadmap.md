## Next high priority — audit polymorphic Inventory tenant scoping

- [ ] Clarify transaction ownership and concurrency safety for creation, batch
  resolution, updates, and deletion. Preserve tenant-aware constraints and
  operation atomicity.
- [ ] Add integration tests for another organization's model, a forged
  in-memory organization, a partially hydrated model, a null organization,
  missing context, direct service calls, inter-tenant polymorphic links, eager
  loading, batch resolution, and concurrency.

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
- [x] `tag`: Type-based attach, detach, and synchronization; single-type read
  scopes; and tests.
- [x] `inventory item`: Creation, update, deletion, and reads through
  `inventory`.
- [x] `inventory stock`: `in`, `out`, `transfer`, and `adjustment` transactions,
  plus stock, lot, movement, and value views.
- [x] `permission`: Available for reading the current context through
  `current-permissions`.
- [x] `user`: Available in auth for login, me, logout, password reset, and
  member-role switching.

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
- [ ] `customer`
- [ ] `supplier`
- [ ] `procurement`
- [ ] `order`
- [ ] Stock-location business concept: the Inventory engine supports
  `HasInventoryLocation`, but the concrete business domain is not exposed yet.

## TODO: product and pricing structure

- [ ] Create a reusable pricing component following the `inventory` pattern so
  prices can be attached to any model.
- [ ] Define the technical contract for a “priceable” model: interface, trait,
  relations, and cross-cutting service.
- [ ] Support multiple prices per model according to a clear type or business
  purpose.
- [ ] Clarify the pricing scope: sale price, purchase price, promotional price,
  channel price, currency price, and dated prices when needed.
- [ ] Decide whether the price repository belongs in `catalog`, `master`, or a
  dedicated module.
- [ ] Add the API and tests for this component before consuming it elsewhere.

## TODO: partners and flows

- [ ] Introduce `customer`.
- [ ] Introduce `supplier`.
- [ ] Introduce `procurement`.
- [ ] Introduce `order`.
- [ ] Define business links: `supplier -> procurement`, `customer -> order`.
- [ ] Decide when these flows officially begin affecting Inventory and Pricing.

## TODO: stock location

- [ ] Name the business concept correctly.
- [ ] Compare naming options: `warehouse`, `store`, `location`, `site`, `depot`,
  and `stock_location`.
- [ ] Avoid `store` if confusion with shop, POS, or storefront is too likely.
- [ ] Avoid `warehouse` if the concept must also cover smaller places such as a
  shelf, van, reserve, pickup point, workshop, or shop.
- [ ] If the concept remains generic, prefer a neutral business term such as
  `stock location`.
- [ ] Once named, create the business domain that cleanly implements
  `HasInventoryLocation` above the Inventory engine.

## Already identified in the code

- [ ] `product`: add category filtering.
- [x] `product variant`: stock tracking and deduction configuration are owned
  by `inventory_items`; disabling tracking is blocked while active stock
  remains.
- [ ] `inventory`: review HTTP authorization for every exposed route,
  including read endpoints, nested resources, policies, permissions, and
  organization boundaries.
- [ ] `inventory`: define the update pattern for `InventoryItem` and
  `InventoryLocation`, including parent-owned updates versus standalone
  Inventory endpoints.
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

- [ ] Run the PostgreSQL concurrency scenario for tag creation and
  synchronization.
- [ ] Implement backend-controlled field selection for response shapes,
  including resource serialization and required relation dependencies.
