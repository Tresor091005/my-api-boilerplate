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
  plus stock, lot, movement, and combined quantity/value summary views.
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

## TODO: organization currencies and exchange rates

- [x] Add an immutable `functional_currency_code` to `organization`: set it
  once and reject in-place changes. Using it for internal prices, stock costs,
  balances, and aggregates remains part of the downstream Inventory and
  business-module work.
- [x] Add organization currency settings through `organization_settings`.
  `enable_currencies` is initialized with the functional currency, and every
  additional currency must exist in the Master currency catalog before it can
  be enabled.
- [ ] Define currency transitions as a migration to a new organization with
  new identifiers, an explicit opening-balance conversion, and closure of the
  previous organization. Do not rewrite historical rows in place.
- [x] Add organization-scoped manual exchange rates with CRUD API, directed
  currency pairs, effective dates, a closed `default` context, uniqueness by
  organization/pair/context/effective date, and protection for effective rates.
- [x] Add an `ExchangeRateService` that resolves the active organization from
  the current organization ID, validates enabled currencies, selects the
  latest applicable directed rate, converts minor units with BCMath, and
  applies explicit half-up rounding. A missing requested context falls back
  to `default` before conversion is rejected.
- [x] Return exchange-rate snapshots from the conversion service, including the
  requested and effective contexts, original and functional currencies and
  amounts, rate, and effective timestamp. Persisting this snapshot on each
  external monetary document remains the responsibility of the business module
  that owns that document.
- [ ] Use conversion at external boundaries: customer payments, refunds,
  supplier invoices, expenses, revenues, imports, and organization
  transitions.
- [x] Keep internal Inventory prices, stock costs, movements, and aggregates in
  the organization's functional currency. Incoming `IN` costs may be supplied
  in an enabled transaction currency, but are converted at the transaction
  boundary and retain an `exchange_metadata` snapshot.
- [x] Enforce non-null functional currency codes on Inventory stocks and
  movements, remove arbitrary currency selection from adjustments, and keep
  currency propagation coherent for `OUT`, `TRANSFER`, and reversals.
- [x] Replace adjustment's currency-specific average-cost selection with the
  functional-currency stock cost.
- [x] Enrich `/stock/summary` with `total_value` and the organization's
  functional `currency_code` per item/location row, so quantity and value are
  returned together.
- [x] Remove the redundant Inventory value endpoints
  (`items/{item}/value` and `locations/{location}/value`).
- [x] Update Inventory contracts, data objects, validation, resources,
  factories, and tests for boundary conversion and historical snapshots.
- [ ] Allow foreign-currency payment only when the organization configuration
  permits it. Creating a second organization is reserved for a genuinely
  separate legal, accounting, or operational lifecycle.
- [ ] Add tests for same-currency operations, disabled conversion, fixed-rate
  conversion, inbound and outbound directions, rounding, rate validity, rate
  snapshots, historical reproducibility, and transition opening balances.

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
- [x] `inventory`: manage `InventoryItem` and `InventoryLocation` lifecycle
  through their owning polymorphic workflows; do not expose standalone
  Inventory lifecycle endpoints.
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
