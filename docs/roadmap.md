## Next steps

Planned on 2026-10-29, in this order:

1. [x] **Finish billing.** Plans, subscriptions paid through checkouts, trials,
   access for subscribers only, and plan features checked where they are used
   (one example per type). See [Billing module](modules/billing.md); what is
   left is listed there under its open questions.
2. **Price and tax on catalog items.**
   - [x] Tax: the `tax` module, and a tax profile or none on each item
     (`catalog_items.tax_profile_id`). See [Tax module](modules/tax.md).
   - [x] Price: named price levels per organization, each in one enabled
     currency, and on each variant, bundle or service one price per level and
     sale unit (`catalog_price_levels`, `catalog_item_prices`).
   - [ ] Prices entered including tax (`tax_inclusive`): decide where the net
     amount of a tax-inclusive price is derived.
3. **Sale, first slice: the sale and its configuration.**
   - Create a sale with its lines and charges. A sale has one currency and one
     price level of that currency, used by all its lines; a customer with a
     price level is sold only at that level. A line copies the
     price (level name, unit, amount) and tax when the sale is made, so later
     price changes leave past sales intact. There are no discounts yet.
   - Services sold by time or count: check that master has those unit groups
     (hour, day, session) before services are sold.
   - Taxes of a sale: calculate its lines through `TaxInterface` and keep the
     result of each line (`TaxResultViewData`, `schema_version` 1, already
     fixed) as a tax snapshot when the sale is confirmed. Add document-level
     rounding (per invoice and rate) on top of the per-bracket rounding.
   - Taxes chosen by context, such as Benin's AIB depending on the customer: a
     `tax_rules` layer selecting the profiles of a sale before calculation.
   - A sale configuration per organization drives the later slices: when
     payment is expected (before, at or after delivery), when the invoice is
     issued (at confirmation or delivery), whether credit is allowed, whether
     delivery is needed (counter or delivered sale), and the default stock
     location.
   - Later slices read that configuration: fulfillment taking stock out,
     invoices, payments and their allocation, then credit notes, returns,
     refunds and adjustments. The data model is in `sale.pdf` and
     `Sale-boilerplate.png`. The payment methods an organization may use are a
     natural choice feature for plans.

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
  `current-permissions` and listing the full catalog through `/v1/iam/permissions`.
- [x] `role`: CRUD API for organization roles, with read-only system roles.
- [x] `user`: Email OTP login and signup, account reads, member-role switching,
  logout, and session management. See [email OTP and sessions](modules/iam.md#email-otp-and-sessions).
- [x] `customer`: Identity CRUD API with organization-scoped polymorphic
  addresses and contacts.
- [x] `tax`: Organization taxes (percentage or fixed, progressive brackets,
  dependencies), tax profiles with standard/custom/zero/exempt treatments,
  `catalog_items.tax_profile_id`, and a batch calculation engine
  (`TaxInterface`) returning the explainable result a document will freeze.

### Partially usable

- [~] `organization`: Minimal service and ID lookup are available, but no true
  business CRUD API is exposed.
- [~] `member`: Present in IAM through `organizationMemberships` and
  `MemberRole`, but there is no dedicated service or management API.

### Not yet industrialized as an autonomous business component

- [~] `price`: Catalog items have sale prices per price level and sale unit;
  there is no generic price component for other models (purchase prices,
  customer prices).
  - TODO: purchase prices are not a kind of `catalog_item_prices`. They belong
    to procurement, per supplier and item (supplier reference, minimum
    quantity, lead time, the supplier's currency), designed with `supplier`.
- [ ] `supplier`
- [ ] `procurement`
- [ ] `order`
- [x] Stock-location business concept: Catalog exposes organization-scoped stock
  locations backed by Inventory's `InventoryLocation` adapter.

## Deferred: pricing

Catalog items have named sale prices per level and sale unit, chosen by the
seller. A reusable pricing component (price lists by customer or period,
quantity breaks, purchasing) will be designed only once the concrete sales and
purchasing requirements are clear.

## TODO: tax follow-ups

- [ ] Optional country packs (importable taxes and profiles) and a tax-regime
  guard (for example, no VAT under Benin's TPS) built on the tax `kind`. It
  waits for regulatory expertise and real users to validate it; the engine
  already takes these as data, so it needs no engine change.

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
- [x] Define a rule for bounded child collections: `config/child-limits.php`
  caps the children of a parent, enforced by PostgreSQL triggers on every write
  path; see [Shared module](modules/shared.md#transaction-locks-and-child-limits).
- [ ] devrais je laisser les models passer entre differentes packages pour les
  relations eloquent et qu'est ce qui est autorisé à passer
- [ ] prendre le temps de faire les tests d'architecture a partir des rules
- [ ] Define a test structure template. Every test of a failure checks its
  kind: a developer mistake (unknown key, missing registration, wrong setup)
  must be a `LogicException` answering 500 and failing tests, never an
  `AssertionException` rendered to the client as a 422. Known case:
  `FeatureException::notRegistered` and the other registry errors in billing.
- [ ] Make the parallel test run reliable: some IAM authentication tests and
  `ChildLimitTriggersTest` fail only when run in parallel, through state the
  processes share (synced triggers, cooldown cache).
- [ ] Profile the slow integration tests: the full suite takes about 21
  minutes on 8 processes (90 minutes of test time); IAM, library and billing
  integration tests take around 10 s each, likely in their per-test setup.
