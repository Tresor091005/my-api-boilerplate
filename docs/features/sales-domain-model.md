# Sales domain model

**Status:** first-release design, before implementation.

This document is the source of truth for the first sales implementation. It
separates the commercial agreement, billing document, fulfillment, and money
movement without imposing one universal sequence on future businesses.

## Vocabulary

The root commercial aggregate is `Sale`, not `Order`.

- `Sale` records the accepted commercial agreement.
- `SaleLine` records the catalog items, quantities, and applied commercial
  values.
- `Invoice` records what the customer is billed.
- `Payment` records money received from a customer.
- `PaymentAllocation` applies received money to an invoice.
- `Fulfillment` records operational execution or delivery.

`Order` remains available for a future pre-sale concept such as a quotation,
shopping basket, reservation, or purchase request that is not yet an accepted
sale.

## First-release scope

The first release supports one commercial rule:

```text
full_payment_before_fulfillment
```

It means that a fulfillment may be prepared before payment, but it cannot be
completed until the invoice is fully paid.

The first release has these additional constraints:

- anonymous sales are allowed, so `Sale.customer_id` is nullable;
- one confirmed sale produces exactly one complete invoice;
- the invoice contains every sale line and the complete sale amount;
- partial invoicing and several invoices for one sale are not supported;
- payments may be made in several parts, but their allocations must fully pay
  the invoice before fulfillment completion;
- inventory reservation is not supported;
- prices are resolved or supplied on the fly and snapshotted on sale lines;
- discount and tax columns exist but application code always writes zero;
- the API does not accept caller-provided discounts or taxes;
- no general ledger or complete accounting system is introduced.

## Creation and confirmation boundary

An invoice must not be issued for a mutable draft.

The domain operation is therefore:

```text
confirm Sale
    -> freeze Sale and SaleLine commercial snapshots
    -> create the complete Invoice and InvoiceLine snapshots
    -> mark the Sale as confirmed
```

These writes belong to one transaction. If the public creation endpoint creates
an immediately confirmed sale, the invoice is created during that same request.
If drafts are exposed, saving a draft does not create an invoice; confirmation
does.

The first-release cardinality is:

```text
Sale 1 -------- 1 Invoice
```

The database must enforce one invoice per sale. "Complete invoice" means that
all sale lines and amounts are represented; it does not mean that the invoice
is already paid.

## Aggregate map

| Model | First-release responsibility |
| --- | --- |
| `Sale` | Commercial root, customer context, currency, totals, status, confirmed conditions, and timestamps. |
| `SaleLine` | `CatalogItem` reference plus immutable name, SKU, unit, quantity, unit price, zero discount, zero tax, and total snapshots. |
| `Invoice` | Complete billing snapshot created when the sale is confirmed. It has its own number, issue date, totals, and lifecycle. |
| `InvoiceLine` | Complete billing copy of one `SaleLine`, retaining a reference to that line. |
| `Payment` | Money received from one customer, independently of any sale or invoice. |
| `PaymentAllocation` | Amount from a `Payment` applied to an `Invoice`. |
| `Fulfillment` | Preparation and completion of the operational delivery for a confirmed sale. |
| `FulfillmentLine` | Quantity from a `SaleLine` prepared or completed by a fulfillment. |

The first happy-path implementation does not require `CreditNote`, `Return`,
or `PaymentRefund`, but their future responsibilities are already distinct:

- `CreditNote` corrects an issued invoice;
- `Return` reverses fulfilled quantities and may drive inverse inventory
  movements;
- `PaymentRefund` records money actually returned to the payer;
- a payment reversal invalidates an erroneous payment without pretending that
  a separate refund movement occurred.

## Catalog item boundary

`CatalogItem` is the canonical operational identity used by `SaleLine`.

```text
SaleLine.catalog_item_id -> CatalogItem.id
```

The catalog currently creates a CatalogItem alongside each ProductVariant and
uses the same UUID for both records. Sales can therefore persist
SaleLine.catalog_item_id without referencing the presentation model.

Sales does not reference `ProductVariant`, `Bundle`, or the inventory adapter.
Before confirmation, the catalog boundary verifies:

- the catalog item belongs to the active organization;
- it is not soft-deleted;
- `is_sellable` is true.

`SaleLine` snapshots every commercial value that must survive a later catalog
change. Renaming, repricing, deactivating, or deleting a catalog item must not
rewrite a confirmed sale or invoice.

## Price, discount, tax, and totals

There is no `CatalogPrice` model in the first release. The applied unit price is
resolved or supplied during sale construction, validated as integer minor-unit
money, and copied to `SaleLine.unit_price`.

For each line:

```text
discount_amount = 0
tax_amount       = 0
line_total       = quantity * unit_price
```

For the sale and invoice:

```text
lines_sub_total = sum(line quantity * line unit_price)
discount_total  = 0
tax_total       = 0
charge_total    = 0 unless an explicit charge is introduced
total           = lines_sub_total + charge_total
```

Persisted money uses integer minor units and an explicit currency code. Never
persist floating-point money.

## Payment ownership and invoice allocation

`Payment` belongs to the customer who paid it. It does not belong to a sale or
invoice.

```text
Customer 1 ---- * Payment
Payment  1 ---- * PaymentAllocation * ---- 1 Invoice
```

A payment may remain fully or partially unallocated. That remainder is an
advance or available customer credit. One payment may settle several invoices,
and one invoice may be settled through several payments.

The amount paid for an invoice is derived from its active allocations. The
available amount of a payment is derived from its amount, active allocations,
refunds, and reversal state. Neither value should be maintained as an
independent mutable balance without a strict consistency rule.

Because the first release creates the invoice with the confirmed sale, every
payment intended for that sale can be allocated directly to its invoice. A
`SalePaymentApplication` is therefore not required in the first release. It
would become relevant only if the system later accepts and reserves money for a
sale before its invoice exists.

## Fulfillment rule

A fulfillment may be created as a draft after sale confirmation. Preparing it
does not change stock and does not require payment.

Completing it requires all of the following:

```text
Sale.status == confirmed
Invoice is active
allocated_invoice_amount >= Invoice.total
requested quantities do not exceed remaining fulfillable quantities
```

Only fulfillment completion triggers the first inventory-relevant operation.
There is no stock reservation.

The initial fulfillment lifecycle is:

```text
draft -> completed
   \-> cancelled
```

`returned` is introduced with the future `Return` workflow rather than used as
a direct edit of a completed fulfillment.

## Sale completion

The initial sale lifecycle is:

```text
draft -> confirmed -> completed
   \-> cancelled
```

A confirmed sale becomes completed when:

- its invoice is fully paid; and
- all lines requiring fulfillment are fully fulfilled.

Items that do not require fulfillment must not keep a sale open artificially.
That capability belongs to the catalog item or its operational adapter and is
evaluated per sale line.

Invoice settlement and fulfillment progress are separate projections. Their
values must not be copied into `Sale.status` as states such as `paid`,
`partially_paid`, or `partially_fulfilled`.

## POS and asynchronous prepaid sales

POS and prepaid sales use the same commercial rule.

An asynchronous prepaid flow is:

```text
confirm Sale and create Invoice
    -> receive and allocate Payment later
    -> complete Fulfillment after full payment
```

A POS checkout is an application-level orchestration:

```text
confirm Sale and create Invoice
    -> create Payment
    -> allocate Payment to Invoice
    -> complete Fulfillment
```

The POS orchestration runs the same domain operations in one request. POS is
not another aggregate and does not merge payment, invoice, and fulfillment
state into `Sale`.

## Reusable sales flow configuration

Flow rules are not configured manually on every sale. An organization defines
reusable sales flow profiles and assigns them by default, channel, POS profile,
customer, or another deliberate context.

At confirmation, the selected profile is resolved and its operational result
is snapshotted on the sale. For the first release:

```text
fulfillment_payment_policy = full_before
required_payment_amount_before_fulfillment = Sale.total
```

The sale may retain `sales_flow_profile_id` for traceability, but authorization
uses the immutable snapshot. Changing a profile later must not alter confirmed
sales.

Only the full-payment profile is implemented initially. Future behavior should
not be represented by one mixed enum because payment gating and invoice terms
are separate dimensions.

### Fulfillment payment policy

| Policy | Fulfillment rule |
| --- | --- |
| `full_before` | The complete invoice amount must be paid before fulfillment completion. |
| `partial_before` | A configured minimum amount must be paid before fulfillment completion. |
| `on_fulfillment` | Payment and fulfillment completion must occur in one orchestration. |
| `none` | Fulfillment completion does not require prior payment. |

### Invoice payment terms

| Terms | Invoice rule |
| --- | --- |
| `due_immediately` | The invoice is payable immediately. |
| `net_days` | The invoice is payable after a configured number of days. |
| `installments` | The invoice is payable according to a future schedule. |

Named business profiles are combinations of these dimensions:

| Business profile | Fulfillment payment policy | Invoice payment terms |
| --- | --- | --- |
| `full_payment_before_fulfillment` | `full_before` | `due_immediately` |
| `partial_payment_before_fulfillment` | `partial_before` | balance terms to be defined |
| `payment_on_fulfillment` | `on_fulfillment` | `due_immediately` |
| `business_credit` | `none` | `net_days` |

`business_credit` is therefore not another kind of payment event. It is a
commercial arrangement in which the organization allows fulfillment before
payment and gives the customer a future invoice due date.

Preparing a fulfillment before payment is not itself credit. If payment is
required before handing over goods and payment plus completion happen together,
the flow is `payment_on_fulfillment`. It becomes credit when fulfillment is
completed while an invoice is intentionally allowed to remain unpaid.

Business credit also requires customer identity, credit exposure rules, due
dates, overdue handling, and collection behavior. It is explicitly outside the
first release.

## Transaction and concurrency invariants

- Sale confirmation and complete invoice creation are atomic.
- A confirmed sale's commercial lines and totals are immutable.
- An invoice total equals its confirmed sale total in the first release.
- Active payment allocations cannot exceed the payment's available amount.
- Active payment allocations cannot exceed the invoice's outstanding amount.
- Fulfillment completion rechecks payment and remaining quantities inside its
  transaction.
- Completing the same fulfillment twice must not create duplicate inventory
  movements.
- Every cross-model operation validates the organization boundary explicitly.

## Deferred capabilities

The following are intentionally documented but not implemented in the first
release:

- partial or staged invoicing;
- several invoices for one sale;
- partial-payment fulfillment profiles;
- payment at fulfillment;
- business credit and credit limits;
- payment schedules and installments;
- payment application to a sale before invoice creation;
- inventory reservation;
- discounts and taxes other than stored zero values;
- credit notes, returns, refunds, and payment allocation corrections;
- accounting ledger postings.

These capabilities must extend the independent aggregates and policy
dimensions above instead of replacing the first-release domain with a single
linear workflow.

---

sale_config
-----------
profile
credit_allowed
payment_terms_days
