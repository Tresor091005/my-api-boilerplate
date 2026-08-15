## High priority — secure tenant scoping for tags

- [x] Review `TagService::resolveOrganizationId()`: `organization_id` on the
  supplied model must never be treated as the security authority. A model may
  be partially hydrated, changed in memory, manually constructed, or belong to
  another organization. The authority is the active organization
  (`currentOrganizationId()`), with explicit validation of the model value when
  available.
- [x] Define behavior for models with a null `organization_id` and partially
  hydrated models. Do not silently turn a global or incomplete model into a
  tenant-scoped model without an explicit business rule. The selected rule is
  that a taggable model must provide `getOrganizationId(): string`; global
  models are not taggable.
- [x] Explicitly reject any mismatch between the active organization and the
  model before reading, creating, modifying, or attaching a tag. Cover this
  with a localized business exception rather than a generic
  `InvalidArgumentException`.
- [x] Verify isolation for `attach`, `detach`, `sync`, and `syncForType`,
  including direct service calls outside `InteractsWithTags` and `HasTags`.
  The caller must authorize the operation, but the service must still validate
  the tenant before its first tenant-owned query.
- [x] Secure the `master_taggables` polymorphic relation. It currently has no
  `organization_id`, so a malformed link could associate a tag from B with a
  model from A or expose that tag through `tags()`. Add local tenant proof to
  relation reads and writes, then evaluate a schema constraint or pivot
  structure that prevents this mismatch at database level.
- [x] Verify `withAnyTagsOfType`, `withAllTagsOfType`, and
  `withoutTagsOfType` scopes. They must remain bounded by the model's
  organization and must not rely solely on trust in existing pivot links.
- [x] Make writes atomic and concurrency-safe: two transactions may execute
  the tag “find then insert” sequence simultaneously. Preserve the tenant-aware
  uniqueness constraint and use an atomic write or handle the collision safely.
- [x] Clarify transaction ownership. Public service methods must remain safe
  when called without `DB::transaction()` by `InteractsWithTags`, a job, or a
  command; `sync` must not detach links before all preconditions are validated.
- [ ] Add integration tests for a model from another organization, a forged
  in-memory `organization_id`, a partially hydrated model, a null
  `organization_id`, missing context, direct service calls, an inter-tenant
  pivot link, scope reads, and concurrent creation of the same tag. Security
  and scope cases are covered; the concurrency scenario still needs to run
  with PostgreSQL available.

## Next high priority — audit polymorphic Inventory tenant scoping

- [ ] Audit `HasInventoryItem` and `InteractsWithInventoryItem`: verify that
  `getOrganizationId()` is the sole ownership source for the host model and
  that forged, partially hydrated, or in-memory models cannot bypass the
  active tenant.
- [ ] Audit `HasInventoryLocation` and `InteractsWithInventoryLocation` using
  the same rules, especially for external location models.
- [ ] Verify `create`, `createMany`, `resolve`, `update`, and `delete`, including
  direct service calls outside HTTP: organization context required, mismatch
  rejected before any tenant-owned query, and localized business exception.
- [ ] Verify `inventoryItem()` and `inventoryLocation()` polymorphic relations
  during lazy loading, eager loading, and batch loading. They must remain
  bounded by the current organization without breaking relations prepared by
  `with()` or `load()`.
- [ ] Verify isolation for `inventory_items` and `inventory_locations`, as well
  as stocks, movements, and transactions reached through these relations. No
  inter-tenant link may be resolved or exposed through a polymorphic identifier
  alone.
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

## TODO: enforce HTTP response-contract coverage

After every API module has declared its response contracts:

- [ ] Make `ResolveResponseContext` fail fast when an API route has no
  registered response contract.
- [ ] Keep an explicitly empty contract valid for routes that intentionally use
  only the method-derived response policy and the service's minimal fallback
  loads.
- [ ] Reject missing contracts before the controller or service executes, so a
  misconfigured HTTP route cannot silently fall back to an incomplete response.
- [ ] Add an integration test proving that a missing HTTP contract is rejected,
  while console, job, scheduler, and direct service callers retain their
  non-HTTP fallback behavior.

## TODO: response shape field selection

- [ ] Define and implement backend-controlled field selection for response
  shapes.
- [ ] Apply the selected fields during resource serialization without exposing
  fields that the active shape excludes.
- [ ] Add resource tests for allowed fields, computed fields, and required
  relation dependencies before enabling the `fields` configuration key.
