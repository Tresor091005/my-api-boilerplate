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
     sale unit (`catalog_price_levels`, `catalog_item_prices`). Every
     organization that prices an item without naming a level gets one built-in
     level (`standard`, in the functional currency), created by that first
     price and never deleted.
   - Prices including tax. A price states the taxes its amount already
     contains, among those of the item's tax profile; the item's other taxes
     are added on top. The choice is per price, not per level, because the
     taxes come from the profile of each item.
     - [x] The taxes a price contains (`catalog_item_price_taxes`), stated when
       the price is created and changed on the price.
     - [x] The tax module derives the amount before tax from such a price,
       line by line with the real quantity; the displayed price stays exact.
       See [amounts that already contain taxes](modules/tax.md#amounts-that-already-contain-taxes).
     - [x] A price is refused when its taxes cannot be taken out of it, also
       when the item's profile, a tax or a tax profile changes later; the way
       out is always to change the taxes the price contains first.
3. [x] **Sale, first slice: the sale up to its confirmation.** Built in the
   `sale` module; see [Sale module](modules/sale.md) for how a sale moves, who
   acts and what its conditions are.
   - [x] The module, the three fixed sale modes and the organization's default
     mode, the customer's payment due date and the walk-in customer, the
     catalog's `sellableItems()` entry point.
   - [x] The draft (create, replace, read, list), then validating, approving,
     rejecting, reopening, abandoning, confirming, cancelling and resuming.
     Confirmation calculates the sale again at its approved prices, asks the
     cashier before keeping them when the catalog moved since, refuses a tax
     that changes an amount, and makes the sale `active`. It creates no
     invoice. Cancelling puts an active sale in `cancelling`; the system makes
     it `cancelled` once what was done is given back.
   - [x] Documentation, API client entries and demo data.
   - A sale has one currency. Each line names the price level and the amount
     the seller chose: the system never picks a price, it checks the one sent
     (the item's price at the customer's own level when it has one, the price
     still the one read). In this slice a sale is in the functional currency
     only, because tax calculation refuses any other.
   - Every sale has a customer. A walk-in customer serves counter sales; it is
     created the first time an organization needs it.
   - A line holds the item, the sale unit and a whole quantity, and copies the
     item name, the SKU, the unit and the unit price; the sale copies the name
     of its price level.
     Its amounts carry the names of the tax result: `amount`, `base_amount`,
     `included_tax_amount`, `added_tax_amount`, `total_amount`, with
     `tax_result` keeping the calculation made through `TaxInterface`
     (`TaxResultViewData`, `schema_version` 2). A confirmed sale never
     changes, so later price changes leave past sales intact. There is no VAT column and
     no discount yet.
   - Services are not sold yet. Before they are, master needs unit groups for
     time and count (hour, day, session): it has none today.
   - **Later slices**, in this order: the goods first, then what is
     monetary, which needs a stable invoicing.
     - Charges, born from fulfillments (delivery, transport): a typed amount
       with a tax profile or none, through the same calculation as a line.
     - Returns (revocations) from a completed fulfillment.
     - Taxes chosen by context, such as Benin's AIB depending on the
       customer: a `tax_rules` layer selecting the profiles of a sale before
       calculation. Until then each line takes the profile of its item and
       keeps the profile used.
     - Invoices, after fulfillment, with document-level rounding (per invoice
       and rate) on top of the per-bracket rounding; then payments and their
       allocation, which let a fulfillment check the paid share; then credit.
     - Credit notes, refunds and adjustments.
   - The data model is in `sale.pdf` and `Sale-boilerplate.png`. The payment
     methods an organization may use are a natural choice feature for plans.
     The rest of what each later slice leaves open is listed in the
     [sale module](modules/sale.md#left-for-later-slices).
4. [x] **Sale, second slice: fulfillment.** The goods of a sale leave the
   stock in the three modes; see [Fulfillment](modules/sale.md#fulfillment).
   - [x] Fulfillments (`draft`, `completed`, `cancelled`) with their lines,
     each from a named warehouse, a quantity in the unit sold in and the lots
     to take if named; cancelling one reverses its inventory transaction.
   - [x] A counter sale delivers everything at confirmation; a bill-and-hold
     sale sets everything aside in its warehouses' customer hold locations,
     managed by the system, and delivers from them; a progress-billing sale
     delivers through drafts.
   - [x] A cancelled sale gives back what it set aside once nothing it
     delivered is still out, and becomes `cancelled`.
   - [x] Inventory's `stockAvailability()` read, checked before any movement,
     and its refusals brought back on the request's fields or naming the line.

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
- [x] `sale`: Three fixed sale modes with the organization's default mode,
  credit sales with an approval and a payment due date, sales with lines
  priced and taxed through the catalog and `TaxInterface`, and the gestures
  from draft to
  `active` (validate, approve, reject, reopen, abandon, confirm, cancel,
  resume) on the shared state machine, and fulfillments taking the goods out
  of stock in the three modes; see [Sale module](modules/sale.md). No invoice
  or payment yet.

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
- [~] `order`: the sale covers the customer's order up to its confirmation;
  delivery, invoices and payments are later slices.
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
