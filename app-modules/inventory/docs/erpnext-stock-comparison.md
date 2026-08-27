# ERPNext Stock comparison

Date of review: 2026-08-27

This document compares the official ERPNext Stock documentation with the
current `app-modules/inventory` implementation. It is a business-rules audit,
not a recommendation to reproduce ERPNext in full. ERPNext is a mature ERP;
some of its features exist to support accounting, purchasing, selling,
manufacturing, compliance, and large warehouse operations that are outside the
responsibility of this reusable inventory module.

## Executive conclusion

The current module has a sound transactional core: tenant-scoped records,
integer base-unit quantities, lot-level balances, FIFO/FEFO/manual allocation,
exact minor-unit costs, transaction idempotency, row locks, transfer linkage,
previews, and reversal transactions. These are valuable design decisions to
keep.

The most important gaps are not the number of features. They are the rules
that protect stock correctness when the module is used under real operational
pressure:

1. The database does not enforce that an item, location, stock, movement, and
   transaction belong to the same organization and refer to a consistent
   item/location pair. The service layer currently carries most of that proof.
2. Batch identity is currently free-form metadata. That is useful as an
   extension point, but it is not equivalent to ERPNext's first-class batch
   traceability, and it cannot safely support recalls, quarantine, or serialised
   items.

The recommended next step is to strengthen persistence integrity before adding
broad ERP features. Then add first-class batch or serial traceability only if
the surrounding application needs it.

Initial balances must enter through a valued `in` transaction. Positive
adjustments deliberately require an existing functional-currency valuation and
cannot create the first stock layer. Historical posting is outside the current
contract; transaction timestamps are generated when the operation runs.

## Official ERPNext material reviewed

- [Stock module overview](https://docs.frappe.io/erpnext/stock)
- [Warehouse](https://docs.frappe.io/erpnext/warehouse)
- [Item](https://docs.frappe.io/erpnext/item)
- [Unit of Measure](https://docs.frappe.io/erpnext/uom)
- [Serial and Batch](https://docs.frappe.io/erpnext/serial-and-batch)
- [Opening Stock](https://docs.frappe.io/erpnext/opening-stock)
- [Stock Transactions](https://docs.frappe.io/erpnext/stock-transactions)
- [Stock Entry](https://docs.frappe.io/erpnext/stock-entry)
- [Stock Reconciliation](https://docs.frappe.io/erpnext/stock-reconciliation)
- [FIFO and Moving Average](https://docs.frappe.io/erpnext/fifo-and-moving-average)
- [Projected Quantity](https://docs.frappe.io/erpnext/projected-quantity)
- [Serial and Batch Bundle](https://docs.frappe.io/erpnext/serial-and-batch-bundle)
- [Landed Cost Voucher](https://docs.frappe.io/erpnext/landed-cost-voucher)

The Stock overview links to the complete Stock area, including stock settings,
valuation, inspections, reports, projected quantity, putaway, and traceability.

## Capability comparison

| Business area | ERPNext approach | Current module | Assessment |
| --- | --- | --- | --- |
| Item identity | Item master supports products, services, raw materials, sub-assemblies, finished goods, variants, groups, brands, manufacturers, barcodes, and item-specific settings. | `InventoryItem` wraps an application model polymorphically and stores SKU, base unit, tracking flag, expiry flag, and deduction strategy. | The wrapper is appropriately reusable. Add fields only when a real consuming workflow needs them. |
| Location model | Warehouses are storage locations and can form a tree such as warehouse > room > row > shelf > bin. | `InventoryLocation` wraps any external model; there is no inventory-owned hierarchy or location type. | Important for warehouse operations, unnecessary for a simple location-aware ledger. |
| Unit of measure | UoM conversions and fractions are supported and configured per item/UoM. | Unit groups and conversion exist through `MasterInterface`; stock quantities are stored as whole base units. Decimal input is accepted only when conversion produces an integer base quantity. | Deliberate integer-base design. Ratios for new custom units are capped at `1,000,000`. |
| Stock representation | Stock Ledger entries update quantity and value for every movement. | Every inbound allocation creates an `InventoryStock` lot with `quantity` and mutable `remaining`; movements form the history. Stock summaries aggregate these lots for normal reads. | Good lot-oriented design. The technical lot remains available for allocation and traceability without burdening daily reads. Make the ledger invariant stronger at the database boundary. |
| Receipts/issues/transfers | Stock Entry has explicit purposes such as Material Receipt, Material Issue, Material Transfer, manufacturing transfer/consumption, manufacture, repack, and scrap. | Transaction types are `in`, `out`, `adjustment`, and `transfer`. | Core generic operations are covered. Keep manufacturing and scrap outside this module until their owning modules exist. |
| Lot allocation | ERPNext supports FIFO and Moving Average valuation, plus serial/batch selection and bundles. | FIFO, FEFO, or manual stock selection; FEFO is default for expirable items. | FEFO is a useful domain decision. Moving Average is a separate valuation policy, not merely another picking strategy. |
| Costing | FIFO, Moving Average, and Standard Cost affect remaining value and COGS; accounting entries are updated continuously. | Exact total cost in minor units, lot unit cost, and cost remainder; no accounting/COGS ledger. | Strong standalone costing foundation, but do not call it ERPNext-compatible valuation yet. |
| Expiry | Batch records carry expiry and support expiry/traceability workflows. | Expiry is a first-class date on stock lots, with FEFO and expiring reads; legacy undated lots are supported. | Good, pragmatic subset. Add explicit expired/quarantine behavior only when required. |
| Batch identity | Batch is a first-class entity used across receipts, issues, traceability, and recalls. | Batch-like data is arbitrary JSON metadata; no unique batch entity or batch-level lifecycle. | Adequate for simple lots; insufficient for regulated traceability. |
| Serial identity | Each serialized unit has a unique serial number and lifecycle. | No serial-number entity or one-unit-per-identity invariant. | Add only for warranty, recall, asset, or regulated use cases. |
| Opening stock | Explicit opening flow; serial/batch stock is entered through a stock entry marked opening. | There is no dedicated opening document. Initial balances must use a valued `in` transaction; a positive adjustment requires an existing functional-currency valuation. | This is a deliberate invariant: `in` establishes stock and value, while adjustment corrects an existing balance. Define the external import mapping before a production migration. |
| Reconciliation | Physical count is compared with book stock and posted at a specific time; it also supports opening stock. | Adjustment quantity is the target final quantity per item/location. | The arithmetic exists, but count evidence, reason, authorization, and audit workflow do not. |
| Projected quantity | Combines current stock, supply, demand, reorder point, and safety stock for planning. | Thresholds are only a future specification; no supply/demand projection. | Planning concern, not a prerequisite for the ledger. |
| Reorder | Auto Material Request and reorder levels can create procurement demand. | No reorder execution; a low-stock endpoint is a TODO. | Useful after demand/order integrations exist. |
| Quality/quarantine | Quality Inspection and rejected/accepted stock can participate in flows. | Metadata may say `quarantine`, but no rule prevents allocation of it. | Metadata must not be mistaken for an enforceable availability state. |
| Transit | ERPNext supports material transfer and goods-in-transit workflows. | Transfer moves stock atomically from one location to another. | Atomic transfer is simpler and safer when transit is not a business state. Add transit only if lead time/ownership matters. |
| Reversal/correction | ERPNext has amendment and valuation repair flows around submitted documents. | Reversal is a new transaction and does not rewrite history; idempotency is enforced. | Excellent core choice for the current system-time transaction contract. |
| Security/tenancy | ERPNext permissions are role/user/company/warehouse aware. | Organization scope is explicit in the module; HTTP routes use authentication, while host permissions remain external. | Tenant isolation is good, but authorization must remain an application boundary as documented. |
| Reporting | Stock ledger, stock level, quick balance, valuation comparison, variance, traceability, negative batch, and where-used reports. | Summary, value, expiring lots, movements, and transactions are available. | Good read base. Add variance and traceability reports before adding dashboards. |

## Resolved decisions

### F-001 — Resolved: stock uses indivisible base units

Stock and movement quantities remain `bigInteger` values. The validator converts
every transaction quantity before persistence and rejects it unless the result
is a whole number of the item's base unit.

Examples:

- `1.5 m` with `mm` as the base unit becomes `1500 mm` and is accepted.
- `0.0005 m` with `mm` as the base unit becomes `0.5 mm` and is rejected.
- `0.5 bottle` with `bottle` as the base unit is rejected.
- `2` cartons with a ratio of `12` become `24` base units and are accepted.

The same validation runs for inbound, outbound, adjustment, and transfer
transactions before any stock mutation. New custom unit ratios are limited to
`1,000,000`; immutable built-in units may retain larger ratios.

## Findings requiring attention

### F-002 — High: organization and aggregate consistency are mostly service-only

The module correctly scopes queries by `organization_id` and validates item,
location, and stock references in `TransactionValidator`. However, the schema
does not use composite foreign keys or equivalent database constraints to prove
that:

- a stock's item and location belong to the same organization as the stock;
- a movement's item, location, stock, and transaction share one organization;
- a movement's item/location pair matches its stock's item/location pair.

This matters because models are directly writable and background jobs,
imports, future integrations, or maintenance SQL can bypass the service. A
single inconsistent row can corrupt summaries and reversal behavior.

Recommended correction:

- Preserve the current service checks, then add database-level constraints or
  a design that makes the organization part of the referenced key.
- Add invariant tests that attempt cross-organization and cross-location
  inserts through every supported write path.
- Keep all raw aggregate queries explicitly tenant-scoped, as required by the
  module rules.

### F-003 — High for regulated goods: metadata is not batch/serial traceability

Each inbound allocation deliberately creates an `InventoryStock` technical lot,
and stock summaries hide that detail from normal reads. The separation between
mutable lot metadata and outbound snapshots is good for audit history. It does
not, however, enforce a business batch identity, status, genealogy, or serial
uniqueness. A JSON value such as `{"batch":"B-123"}` cannot reliably answer
recall, quarantine, or serial lifecycle questions.

Recommended staged approach:

1. Keep `InventoryStock` as the technical lot and stock summaries as the normal
   aggregate read model.
2. Add a stable business batch code and make it unique per tenant/item only
   when batch-level traceability is required.
3. Add lot status and allocation eligibility if quarantine/release matters.
4. Add a separate serial unit model only for items that require unit-level
   identity; do not force serial complexity on every item.

### F-004 — Medium: adjustment is arithmetic, not yet a controlled stock count

The adjustment implementation correctly treats quantity as the target final
quantity and uses weighted average cost for positive deltas. That is a strong
primitive. A positive adjustment deliberately requires an existing valuation;
the first valued stock must use an `in` transaction. ERPNext's reconciliation
workflow adds operational controls such as count context and an auditable
reason.

The module should at least require or strongly encourage a stable adjustment
reason/reference and preserve who/when/where the count came from. Approval and
count sessions belong in a higher-level warehouse workflow if needed.

### F-005 — Medium: availability status is not modeled

The module has `remaining` and arbitrary metadata, but no explicit distinction
between available, quarantined, damaged, blocked, or quality-hold stock. If
metadata is used for these states, every deduction strategy must consistently
filter it; currently the metadata contract does not establish such a rule.

Choose one of these deliberately:

- keep all lots allocatable and document metadata as informational only; or
- add an explicit allocation status and make FIFO, FEFO, manual selection,
  summaries, and reversals respect it.

### F-006 — Medium: ledger immutability is not enforced at the persistence edge

There are no public movement/transaction update routes, and reversals preserve
history. That is good. The Eloquent models remain writable, however, and there
is no database trigger or immutable append-only policy visible in the module.

If direct model writes are intentionally forbidden, encode that in the public
contract and architecture tests. If the database can be reached by imports or
multiple services, add stronger protection or a single append-only write
boundary.

## Rules to keep from the current design

These decisions are already valuable and should survive any ERPNext-inspired
refactor:

- explicit organization resolution and rejection of cross-organization
  references;
- transaction-scoped writes with `lockForUpdate()` for selected stock rows;
- idempotency key plus payload hash for retried operations;
- exact minor-unit total costs and a remainder strategy;
- lot-level allocation rather than only an aggregate balance;
- FEFO for expirable goods, with deterministic handling of undated legacy lots;
- transfer `link_id` connecting source and destination allocations;
- reversal as a compensating transaction rather than mutation of history;
- previews that execute the real transaction path and roll back;
- immutable outbound snapshots of lot metadata;
- rejecting inactive items/locations for new movements while retaining history.

## ERPNext features that should not be copied yet

These are legitimate ERPNext capabilities but would add disproportionate
complexity to this module unless the host application already needs them:

- accounting ledger integration, COGS, perpetual/periodic inventory, and
  landed-cost allocation;
- manufacturing consumption, work orders, BOMs, repack, disassembly, and
  production planning;
- delivery trips, packing slips, shipping rules, and route optimization;
- a full warehouse tree down to bins and putaway rules;
- broad pricing, supplier, customer, and sales-order concerns;
- every ERPNext report and dashboard;
- serial tracking for items that only need lot-level tracking.

These should be separate bounded contexts or integrations. The inventory
module should expose stable contracts for them rather than absorb their data
models.

## Suggested implementation order

1. Strengthen F-002 with database constraints or an equivalent aggregate-key
   design, then test direct and service-mediated writes.
2. Add explicit adjustment/count reason and a stock availability policy.
3. Add first-class batch/serial traceability only for the affected item classes.
4. Add reorder/projected quantity after supply and demand sources are modeled.
5. Add accounting, manufacturing, quality, transit, or warehouse hierarchy as
   separate modules when their business owners and workflows are present.

## Final judgment

The module is not missing “all of ERPNext.” It already contains the hardest
part of a useful generic stock ledger. Its immediate risk is persistence
invariant correctness, not feature count. ERPNext should be used as a source of
tested business concepts and edge cases, especially reconciliation,
traceability, and valuation, not as a schema to copy wholesale.
