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
3. **Sale, first slice: the sale up to its confirmation.**
   - A new `sale` module holds the sale, its lines and the sale
     configurations. How a sale moves, who acts and what its conditions are is
     agreed in [Sale flow](#sale-flow).
   - The slice builds everything up to `active`: the sale configurations, the
     sale and its lines, then validating, approving, rejecting, withdrawing,
     reopening, abandoning and confirming. Confirmation runs the two checks,
     copies prices and taxes and makes the sale `active`. It creates no
     invoice and takes no stock out yet; until those slices exist, cancelling
     an active sale closes it at once, since nothing is to be undone.
   - A sale has one currency and one price level of that currency, used by all
     its lines; a customer with a price level is sold only at that level. In
     this slice a sale is in the functional currency only, because tax
     calculation refuses any other. A customer tied to a level in another
     currency cannot be served; the way out is to change their level.
   - Every sale has a customer. A walk-in customer serves counter sales; it is
     created the first time an organization needs it.
   - A line holds the item, the sale unit and a whole quantity, and copies the
     item name, the SKU, the unit and the unit price; the sale copies the name
     of its price level.
     Its amounts carry the names of the tax result: `amount`, `base_amount`,
     `included_tax_amount`, `added_tax_amount`, `total_amount`, with
     `tax_result` keeping the calculation made through `TaxInterface`
     (`TaxResultViewData`, `schema_version` 2). Confirmation freezes them, so
     later price changes leave past sales intact. There is no VAT column and
     no discount yet.
   - Services are not sold yet. Before they are, master needs unit groups for
     time and count (hour, day, session): it has none today.
   - Later slices, in this order:
     - Charges. The amount is typed; a charge has a tax profile or none and
       goes through the same calculation as a line. A charge may be added to
       a confirmed sale: what that means once the single invoice of the first
       invoicing mode exists is settled then.
     - Taxes chosen by context, such as Benin's AIB depending on the
       customer: a `tax_rules` layer selecting the profiles of a sale before
       calculation. Until then each line takes the profile of its item and
       keeps the profile used.
     - Invoices, with document-level rounding (per invoice and rate) on top
       of the per-bracket rounding; then payments and their allocation; then
       fulfillment taking stock out, which needs both to check the paid
       share.
     - Credit notes, returns, refunds and adjustments.
   - The data model is in `sale.pdf` and `Sale-boilerplate.png`. The payment
     methods an organization may use are a natural choice feature for plans.

## Sale flow

Agreed on 2026-11-15, before any code. `dossier-A3.pdf` describes the engine
underneath (sale, delivery, invoice, payment); this section says how a sale
moves through it. It moves to the module document once the `sale` module
exists.

### Gestures and actors

- A sale moves by four gestures: sell (prepare, validate, confirm), invoice,
  collect (receive money and allocate it to an invoice) and deliver (take the
  goods out of stock). The invoice is never a gesture of its own: it is born
  with another one.
- Actors are roles. Each gesture has its permission, and nothing is written
  for a seller, a cashier or a storekeeper. One person holding every
  permission sends the gestures in a single action.
- What passes from one actor to the next is an approved sale. It is locked, so
  nobody receives different information.
- In the first invoicing mode the invoice is born at confirmation. To leave no
  invoice behind a customer who never pays, the one who collects is the one
  who confirms.

### Sale conditions

An organization keeps several reusable sale configurations, one of them the
default. A sale copies its conditions from one configuration when it is
created. They are frozen once the sale is approved, so changing a
configuration never touches sales already under way.

- **Invoicing.** `invoice_on_confirmation`: one invoice for the whole sale,
  born at confirmation. `invoice_on_fulfillment`: one invoice per delivery,
  born when the delivery is prepared, before the goods leave.
- **Required payment share**, from 0 to 100 %. The goods of a delivery leave
  only when that share of its invoice is paid: the invoice of the sale in the
  first mode, the invoice of that delivery alone in the second. 100 is cash, a
  share in between is a deposit, 0 is credit.
- **Payment delay** in days for what remains. The value typed on the sale
  wins, then the customer's, then the configuration's. A sale that leaves
  something to pay later is refused when all three are empty; the way out is
  to set a delay or to require 100 %.
- **Delivery.** Immediate: everything leaves at confirmation, and both
  invoicing modes then give the same result. Deferred: one or several
  deliveries later.

Not covered: refusing an order until its deposit is paid. The payment is
checked when goods leave, not at confirmation. Until then, the one who
collects the deposit is the one who confirms.

### Statuses

| Status | Meaning |
| --- | --- |
| `draft` | Freely editable. |
| `waiting_approval` | Locked, waiting for someone allowed to approve. |
| `rejected` | Refused with a reason; reopened to be corrected, or abandoned. |
| `approved` | Locked, authorized and ready to be confirmed. |
| `active` | Confirmed and being served. |
| `completed` | Nothing remains to do. |
| `cancelling` | Total cancellation under way: what was done is being undone. |
| `cancelled` | A confirmed sale entirely undone. |
| `abandoned` | Dropped before confirmation. |

- The number is given at creation (`BusinessNumberService`). A sale dropped
  before confirmation is abandoned, never deleted, so the sequence has no gap.
  `cancelled` is reached only from a confirmed sale, `active` or `completed`,
  through `cancelling`.
- The system alone sets `completed`: everything sold is delivered, invoiced
  and paid. A return or a credit note brings the sale back to `active` until
  the exchange or the refund is done; it is then `completed` again by itself.
- Paid and delivered are not statuses. Two indicators on the sale say where
  payment and delivery stand, and the work lists (to collect, to deliver)
  filter on them.
- Giving up what remains to deliver closes a sale early; it then becomes
  `completed` by itself. A mark on the sale says a remainder was given up, and
  each line keeps the quantity given up; it is not a status. In the first
  invoicing mode that remainder is already invoiced, so giving it up goes
  through a credit note.

### Approval

Every sale goes through `approved`, even with nothing to approve. Whoever
holds the approval permission approves by acting; the others wait for someone
who holds it.

| Gesture | From | To |
| --- | --- | --- |
| Validate | `draft` | `approved` when nothing needs approval or the one validating may approve; otherwise `waiting_approval` |
| Approve | `waiting_approval` | `approved` |
| Reject, with a reason | `waiting_approval` | `rejected` |
| Withdraw | `waiting_approval` | `draft` |
| Reopen | `rejected`, `approved` | `draft`; the approval is lost |
| Confirm | `approved` | `active` |
| Abandon | any status before `active` | `abandoned` |

- What needs approval today: a required payment share lower than the
  configuration's, and nothing else. A longer delay typed on the sale needs
  none. Later reasons join the same list: the customer's maximum debt, the
  maximum credit on one sale, overdue invoices, a special price, a quantity.
- Someone who may do everything confirms a draft directly when validation
  would pass by itself.
- Two checks protect what was read. The one who validates sends the version of
  the draft they read. A draft keeps the amounts of its last calculation;
  confirmation recalculates prices and taxes and names any amount that
  differs. Either check refuses when something changed; the way out is to
  reopen, read again and validate.

### First slice: how it is built

Agreed on 2026-11-15. It is built on one branch, `sale`, and reviewed as a
whole at the end.

- **Module.** `app-modules/sale`, depending on shared, master, organization,
  catalog, customer and tax.
- **What shared and master already offer is used.** The shared state machine
  carries the statuses: states and events are enums of the sale module and
  the graph is bound in its provider. `BusinessNumberService` gives the number
  (`S-{YEAR}-{SEQ}`, reset yearly, set in the numbering configuration).
  `TransactionLock`, the bulk validation rules, cursor pagination and the
  money and unit conversions of master are used as elsewhere.
- **Tables.**
  - `sale_configurations`: name, default flag, invoicing mode, required
    payment share, payment delay (may be empty), delivery mode. At most 20 per
    organization.
  - `sale_sales`: number, status, version, customer, price level, currency,
    configuration (may be empty) with its four conditions copied, the delay
    typed on the sale and the delay applied, the reasons an approval is
    needed, totals.
  - `sale_lines`: item, sale unit, whole quantity, the copies and the amounts
    listed in step 3 above, the tax profile used and `tax_result`. One line
    per item and unit, at most 250 per sale.
  - `sale_transitions`: one row per gesture, with the event, the status before
    and after, who, when and the reason given. A small history beside the
    sale, until a global audit trail exists; the sale itself keeps no "who"
    column.
  - `customer_customers` gains `payment_delay_days` and `is_walk_in`.
- **Copies.** A sale copies whatever it must still show when the source
  changes: the names of the customer, the price level and the configuration;
  on a line, the name of the item (product name and option values for a
  variant, bundle name), its SKU and type, the unit, the unit price and the
  taxes it contains.
- **Catalog.** A public entry point gives the sale, for a price level and a
  list of (item, unit), the name, SKU, tax profile, price and included taxes
  of each, with a fixed number of queries.
- **Calls**, under `/v1/sale`:

  | Gesture | Call | Permission |
  | --- | --- | --- |
  | List, read | `GET sales`, `GET sales/{sale}` | `list`, `retrieve` |
  | Create a draft | `POST sales` | `create` |
  | Replace the draft | `PUT sales/{sale}` | `update` |
  | Validate, withdraw, reopen, abandon | `POST sales/{sale}/validate`, `/withdraw`, `/reopen`, `/abandon` | `update` |
  | Approve, reject | `POST sales/{sale}/approve`, `/reject` | `approve` |
  | Confirm | `POST sales/{sale}/confirm` | `confirm` |
  | Cancel | `POST sales/{sale}/cancel` | `cancel` |
  | Configurations | read and write on `configurations` | those of the model |

- **Choices.**
  - A draft is sent whole at each write, with the version read; there is no
    call per line. Each write recalculates everything and keeps the amounts.
  - An organization without a configuration sells with built-in conditions:
    invoice at confirmation, 100 %, immediate delivery.
  - The same item and unit twice on one sale is refused.
  - Every status is in the list from the start; `completed` and `cancelling`
    are reached with the later slices.
  - Every step comes with tests, one of which counts queries: 2 lines or 50
    must cost the same.
- **Steps.** (1) The module and the sale configurations. (2) The customer's
  payment delay and the walk-in customer. (3) The catalog entry point.
  (4) The draft: create, replace, read, list. (5) Validating and approving.
  (6) Confirming, abandoning, cancelling. (7) Documentation, API client, demo
  data, architecture tests.

### Left for later slices

- Where the credit limits live (customer or configuration). None comes with
  the first slice.
- The default stock location of an immediate delivery.
- An immediate delivery without stock refuses the confirmation. The ways out:
  a deferred delivery, a corrected stock, or dropping the line or the sale.
- Giving up everything when nothing was delivered is a cancellation, not an
  early close.
- In the second invoicing mode a service must be delivered too, otherwise it
  is never invoiced.

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
