# Sale module

A sale records what a customer buys, at which prices and under which
conditions. It is the pivot of the engine drawn in `dossier-A3.pdf`: delivery,
invoices and payments all start from it. The sale is built up to its
confirmation, then fulfilled: its goods leave the stock, in the three modes.
It creates no invoice yet. The data model is drawn in `sale.pdf` and
`Sale-boilerplate.png`.

The module lives in `app-modules/sale` and depends on shared, master,
organization, catalog, customer, tax and inventory. Routes are listed in
[the endpoint reference](../api/endpoints.md#sale).

## Gestures and actors

- A sale moves by four gestures: sell (prepare, validate, confirm), invoice,
  collect (receive money and allocate it to an invoice) and deliver (take the
  goods out of stock). The invoice is never a gesture of its own: it is born
  with another one. Only selling exists in this slice.
- Actors are roles. Each gesture has its permission, and nothing is written
  for a seller, a cashier or a storekeeper. One person holding every
  permission sends the gestures in a single action.
- What passes from one actor to the next is an approved sale. It is locked, so
  nobody receives different information.
- In the modes that invoice at confirmation (`counter`, `bill_and_hold`)
  the invoice is born at confirmation. To leave no invoice behind a customer
  who never pays, the one who collects is the one who confirms.

## Sale conditions

A sale runs in one of three fixed modes, `SaleMode`. The mode says when the
invoice is born and when the goods leave. Nobody creates or edits a mode, and
a sale never sets its invoicing or delivery itself: both follow from its mode,
and a payload that sends them is ignored.

| Mode | Invoicing | Delivery |
| --- | --- | --- |
| `counter` | `invoice_on_confirmation` | `immediate` |
| `bill_and_hold` | `invoice_on_confirmation` | `deferred` |
| `progress_billing` | `invoice_on_fulfillment` | `deferred` |

- **Invoicing.** `invoice_on_confirmation`: one invoice for the whole sale,
  born at confirmation. `invoice_on_fulfillment`: one invoice per delivery,
  born when the delivery is prepared, before the goods leave.
- **Delivery.** Immediate: everything leaves at confirmation. Deferred: one or
  several deliveries later.
- **The mode of a sale** is the one typed on the draft. When none is typed, it
  is the default mode of the organization, read at each write of the draft: a
  replacement that leaves `mode` out takes the default again. Changing the
  default never touches a sale already written, since each sale keeps its own
  mode. Confirmation keeps it too.
- **The default mode of the organization** is its only sale setting
  (`sale_settings`, `GET` and `PUT /v1/sale/settings`). An organization that
  never wrote it sells in `counter`.
- **Required payment share**, from 0 to 100 %, typed on the sale. It is 100 %
  unless the sale lowers it: when `required_payment_percent` is left out or
  sent empty, 100 applies. The goods of a delivery leave only when that share
  of its invoice is paid: the invoice of the sale in the modes that invoice at
  confirmation, the invoice of that delivery alone in `progress_billing`.
- **Credit.** Any share below 100 is a credit, whether it is a deposit or 0.
  A credit:
  - needs an approval, for the reason `credit_granted` (see
    [Approval](#approval));
  - goes only to a named customer. The walk-in customer, also when it is
    chosen by leaving `customer_id` out, never gets a credit: the draft is
    refused as soon as it is written, and again at validation if it ever
    reached the sale some other way;
  - needs a payment due date.
- **Payment due dates** are dates, never delays in days, and two are
  independent of each other:
  - the date typed on the sale (`payment_due_date`), kept as sent;
  - the date of the customer (`customer_customers.payment_due_date`), which is
    only used when the sale has none.

  A date from today on is a date that counts; a past date does not. "Today" is
  the current day in the timezone of the organization
  (`OrganizationInterface::today()`). A date typed on a sale must be from
  today on when it is written.

  The date that applies is never stored. It is read when needed: the date
  typed on the sale, else the customer's current date, and only for a credit.
  A sale paid at once has none. The customer's date is never copied onto the
  sale: a credit sale without a date of its own follows it, so when the
  customer's date is moved, those sales move with it. At validation, the credit is refused if the
  typed date has passed since the draft was written, or if no date applies (the customer's
  is empty or past). The way out is to type a date on the draft, to set one on
  the customer, or to require 100 %.

Confirmation also refuses a credit whose date that applies has passed since
validation; for an approved sale the way out is to reopen it, type a date from
today on and validate again.

The customer's date can be moved freely but cannot be emptied while credit
sales follow it: those in `waiting_approval`, `approved`, `active` or
`cancelling` with no `payment_due_date` of their own and a share below 100 %.
The customer module asks the sale module through
`CustomerUsageInterface`, without depending on it. The way out is to
keep a date on the customer and move it, since confirmed sales cannot be changed.

A customer with sales under way (the same four statuses) cannot be deleted,
through the same contract. The way out is to finish or abandon those sales,
or to deactivate the customer instead.

An inactive customer takes no new commitment, and what is confirmed goes on.
Writing a draft, validating, approving and confirming refuse an inactive
customer, and validating and approving also refuse one deleted since the draft.
A customer can be deactivated at any time, with sales under way: a sale waiting
for approval or approved is refused at its next gesture, and can still be
rejected, reopened or abandoned. An `active` or `cancelling` sale goes on
(cancelling and resuming, then delivery, invoicing and payment in later
slices).

Not covered: refusing an order until its deposit is paid. The payment is
checked when goods leave, not at confirmation. Until then, the one who
collects the deposit is the one who confirms.

## Statuses

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

- The number is given at creation (`BusinessNumberService`, key `sale`,
  `S-{YEAR}-{SEQ}` with a six-digit sequence, such as `S-2026-000001`, reset
  yearly). A sale dropped before confirmation is abandoned, never deleted, so
  the sequence has no gap. `cancelled` is reached only from a confirmed sale.
- `cancel` puts an active or completed sale in `cancelling`. `resume` takes
  it back to `active` while the cancellation is not finished; whatever was
  already undone stays undone, since a partial correction does not cancel a
  sale. Only the system makes it `cancelled`, once what was invoiced, delivered and paid
  is given back, and sets `cancelled_at`. Today only fulfillments exist: the
  sale is `cancelled` as soon as nothing it delivered is still out, at the
  `cancel` itself or when its last completed fulfillment is cancelled (see
  [Fulfillment](#fulfillment)). The transition is recorded with the event
  `complete_cancellation`, which a refusal never lists among the gestures.
- Only the system sets `completed`: everything sold is delivered, invoiced and
  paid. It is not reached yet: it comes with invoices and payments. A return or a credit note will bring the sale back to
  `active` until the exchange or the refund is done; it is then `completed`
  again by itself.
- Paid and delivered are not statuses. Two indicators on the sale will say
  where payment and delivery stand, and the work lists (to collect, to
  deliver) will filter on them.

The statuses run on the [shared state machine](shared.md#state-machine); the
sale is the first module to use it. `SaleStatus` and `SaleEvent` are enums of
this module and the frozen graph is bound in `SaleServiceProvider` as the
singleton `sale.state.machine`, given to `SaleWorkflowService`. A guard
receives a `SaleTransitionContext`: whether the sale needs an approval, and
whether the one acting may approve. Each gesture locks the sale row, moves it
and writes its transition in one transaction. Before checking anything, a
gesture the status never allows is refused, so the answer does not depend on
the data.

| Event | From | To |
| --- | --- | --- |
| `validate` | `draft` | `approved` when nothing needs approval or the one validating may approve; otherwise `waiting_approval` |
| `approve` | `waiting_approval` | `approved` |
| `reject` | `waiting_approval` | `rejected` |
| `reopen` | `waiting_approval`, `rejected`, `approved` | `draft`; the approval and its reasons are lost |
| `confirm` | `approved` | `active` |
| `abandon` | `draft`, `waiting_approval`, `rejected`, `approved` | `abandoned` |
| `cancel` | `active`, `completed` | `cancelling` |
| `resume` | `cancelling` | `active` |

`create` is the ninth event of `SaleEvent`: it is kept in the first transition
of a sale but is not part of the graph.

## Approval

Every sale goes through `approved`, even with nothing to approve. Whoever
holds the `approve` permission approves by acting; the others wait for someone
who holds it.

- What needs approval today: a credit, any required payment share below
  100 %, and nothing else. The reason is `credit_granted`. It is kept on the
  sale (`approval_reasons`, a list) and settled at validation. A sale paid
  at once has no reason, and one that goes back to `draft` loses them. A
  credit needs the approval even when the date typed is far away.
- Later reasons join the same list: the customer's maximum debt, the maximum
  credit on one sale, overdue invoices, a special price, a quantity.
- Validating and confirming are two separate actions: the seller validates,
  the cashier confirms, and `confirm` is refused on a draft. One person
  holding every permission sends the two in a row, and a front end may chain
  them if it wants.
- Two checks protect what was read. The one who validates sends the version
  of the draft they read. A draft keeps the amounts of its last calculation;
  confirmation recalculates the taxes and names any line whose amounts
  differ. Either check refuses when something changed; the way out is to
  reopen, read again and validate.
- The approval covers the prices: a catalog price that moves after it does not
  change the sale on its own. Confirmation asks the cashier instead (see
  below).

### What confirmation does

Confirmation first checks that the due date that applies, for a credit, has
not passed. It then calculates the sale again with today's catalog and taxes,
at the approved unit prices, and refuses if the customer may no longer buy or
a line is no longer sellable at its level (see the refusals below). A price
removed from the line's level counts as no longer sellable. The mode, the
share and the due dates stay as they were.

It then looks for catalog prices that moved since the approval: a line whose
level now holds another price, or whose item is now priced at a level the
customer was tied to since. Unless the call sends `keep_approved_prices: true`,
it refuses, naming each line with its approved price and the current one (and
the customer's level when that is where the price comes from); the context
lists them as `price_changes`. The cashier then either confirms again with
`keep_approved_prices: true`, and the sale is sold at the approved prices, or
reopens the sale to take the new ones, which needs a new approval. The flag
covers prices only.

Last, it compares, for each line, what the customer pays: the unit price, the
five amounts and the amount of each tax. A renamed item, tax or level, or a
changed tax bracket the line does not reach, does not count. A tax that changes
an amount refuses, even with `keep_approved_prices`. It names the lines that
differ by their SKU and unit code. If nothing differs, the sale stays as it was
approved, copies included: it only sets `confirmed_at` and becomes `active`.
The version does not grow. It creates no invoice.

Then the goods: a counter sale delivers everything and a bill-and-hold sale
sets everything aside, from the sources the call gives (see
[Fulfillment](#fulfillment)). A progress-billing sale ignores them. All of it
happens in the confirmation's transaction: if the stock is lacking, nothing is
confirmed and nothing moves.

## Fulfillment

A fulfillment is the goods of a sale that leave together (`sale_fulfillments`,
numbered `FL-{YEAR}-{SEQ}`, with its `sale_fulfillment_lines`). It is `draft`,
then `completed` when its goods leave the stock, then `cancelled` when they come
back. Only a draft is changed or deleted; a completed fulfillment is never
edited, it is cancelled.

### Sources

Wherever goods leave, the request says where from, line by line:

```json
"lines": [
  {
    "sale_line_id": "…",
    "sources": [
      { "stock_location_id": "…", "quantity": 6 },
      { "stock_location_id": "…", "quantity": 4, "stock_ids": ["…", "…"] }
    ]
  }
]
```

- `stock_location_id` is a warehouse, always named: there is no default
  warehouse. A line may take from several warehouses, each once.
- `quantity` is a whole number in the unit the line was sold in.
- `stock_ids` is optional: the lots to take, in that order. Left out, inventory
  takes the lots by the item's strategy (FIFO, or FEFO for an item that
  expires). A lot must be of that item in that location.
- A line whose item has no tracked stock needs no warehouse and moves no
  stock; a lot is refused on it.
- Each line once per request, at most 250 lines and 20 sources per line.

The checks run under the sale's lock, with a fixed number of queries, before
anything moves: whether each line is the sale's, whether the sources add up as
they must, whether each warehouse is an active warehouse of the organization
and each lot is there, and whether the stock is enough. A request problem is a
422 on its own field (`lines.0.sources.1.stock_ids.0`); missing stock is a
`SaleException` naming the line, the warehouse, and what was asked and what is
there, in the unit sold in. Inventory checks the stock again under its own lock
when it records the movement, and its refusals come back the same way.

### By mode

| Mode | At confirmation | Afterwards |
| --- | --- | --- |
| `counter` | `lines` is required: each tracked line is listed and its sources add up to the whole line. A fulfillment is created `completed` and the stock leaves. An untracked line left out is delivered whole, with no warehouse. | Nothing, unless a fulfillment is cancelled: then what came back may leave again through a new fulfillment. |
| `bill_and_hold` | `lines` is required the same way, for tracked lines only (an untracked line is refused: nothing of it is set aside). The goods move from each warehouse to its customer hold location; `sale_holds` keeps what was set aside, per line and warehouse. | Fulfillments name the warehouse the goods were set aside in, and take from its hold location, never more than is still held there for the line. |
| `progress_billing` | `lines` is ignored; nothing moves. | Fulfillments take from the warehouses, never more than is left to deliver on each line. |

What is left to deliver on a line is its quantity less what completed
fulfillments delivered; drafts do not count. A line renders what was delivered
(`delivered_quantity`) and what is left (`left_to_deliver`), in the unit sold
in. A hold renders what is still set aside for its line in its warehouse
(`held_quantity`): `GET sales/{sale}?include=lines,holds` is the follow-up of
a sale delivered later.

### Customer hold locations

Goods sold and left with us (`bill_and_hold`) are moved, at confirmation, to a
location beside their warehouse: its customer hold location
(`catalog_stock_locations.kind` = `customer_hold`, `held_for_location_id` = the
warehouse). The catalog creates it the first time a sale needs it
(`CatalogInterface::customerHoldLocation`), named after the warehouse ("Main
Warehouse (held for customers)"). Other sales, transfers and bundle assembly
never see it, so what is set aside is out of their reach without any further
check. It is visible to users (stock locations list it, with `kind`), but nobody
changes, deletes, transfers from or assembles in it, and it does not count
against the plan's stock locations. Deleting the warehouse deletes it, and is
refused while it still holds stock.

### Gestures

- Creating or replacing a draft checks its sources as above; it touches no
  stock. The sale must be `active`.
- Completing checks the draft again, with the stock as it is now, then takes
  the goods out in one inventory transaction (the fulfillment's id is its
  idempotency key, so completing twice never moves stock twice). Each line
  keeps what its goods cost when they left (`total_cost`).
- Cancelling a completed fulfillment reverses its inventory transaction: the
  goods go back to the lots they left, at the same cost; for a bill-and-hold
  sale, back to the hold location. It is allowed on a sale being cancelled.
- Deleting removes a draft.

### Cancelling a sale

`cancel` makes the sale `cancelling`. While anything it delivered is still out,
it stays so: no fulfillment is created or completed, and cancelling its
completed fulfillments brings the goods back. Once nothing is out, the system
gives back to each warehouse what is still set aside (one inventory transfer;
`sale_holds.released_quantity`), deletes the drafts and makes the sale
`cancelled`. A sale with nothing delivered is `cancelled` at once.

## What a sale holds

Amounts are stored in minor units of the currency (`bigint`) and rendered as
decimal strings, like prices.

### `sale_settings`

| Column | Meaning |
| --- | --- |
| `organization_id` | Unique: one row per organization at most. |
| `default_mode` | One of the three modes. |

The row is created by the first `PUT /v1/sale/settings`, in one statement, so
two first writes at the same moment cannot both insert. Without a row the
default mode is `counter`. The row is never deleted.

### `sale_sales`

| Column | Meaning |
| --- | --- |
| `number` | Unique in the organization. |
| `status` | One of the nine statuses. |
| `version` | Starts at 1 and grows by one with each write of the draft. |
| `customer_id`, `customer_name` | The customer and the copy of its name. |
| `currency_code` | The currency of the sale: every line's price level is in it, today the functional currency. |
| `mode` | One of the three modes. There is no `invoicing` or `delivery` column: both are derived from the mode when the sale is rendered. |
| `required_payment_percent` | 0 to 100, 100 unless the sale lowers it. |
| `payment_due_date` | The date typed on the sale, or empty. It is never filled from the customer; the date that applies is read when needed (`due_date` in the API). |
| `approval_reasons` | Why an approval is needed (a list, today only `credit_granted`), or empty; settled at validation and emptied when the sale goes back to `draft`. |
| `lines_base_total`, `lines_tax_total`, `lines_total`, `total` | Sums of the lines: before tax, taxes (included and added), to pay; `total` equals `lines_total` until charges exist. |
| `confirmed_at`, `cancelled_at` | When the sale was confirmed, and when the system made it `cancelled`. |

A sale is never deleted. The customer, and on each line the item and the
price level, are protected by composite foreign keys, so none of them leaves
the database while a sale holds it. A sale is read with its customer even when that
customer was deleted since.

### `sale_lines`

| Column | Meaning |
| --- | --- |
| `position` | The order in which lines were sent, from 1. |
| `catalog_item_id`, `quantity`, `display_unit_code` | The item, its quantity and the unit it is sold and priced in. Like bundle items and stock transfer lines, `quantity` is stored in the ratio-1 base unit of the item's unit group (3 `kg` is stored as 3 000 000, the base of mass being the milligram) and rendered in the unit sold in; a quantity is sent whole, from 1 to 1 000 000 000, in that unit. `display_unit_code` is rendered as `unit_code`. |
| `item_type`, `item_name`, `item_sku`, `unit_name`, `unit_amount` | Copies: what the line must still show when the catalog changes. The name of a variant is its product name followed by its option values, such as `Shirt (Red, L)`, in the order given to the variant. `unit_amount` is the price of one unit sold in, not of a base unit. |
| `price_level_id`, `price_level_name` | The price level the line is sold at, chosen by the seller, and the copy of its name. A sale may hold lines at several levels. |
| `tax_profile_id` | The tax profile used, empty for an item without one. |
| `amount` | `unit_amount` times the quantity in the unit sold in. |
| `base_amount`, `included_tax_amount`, `added_tax_amount`, `total_amount` | Before tax, taxes the price already contains, taxes added on top, to pay. |
| `tax_result` | The calculation kept as it was (`TaxResultViewData`, `schema_version` 2), in minor units, with the taxes the price contains. The API renders it with its amounts as decimals. |

One line per item and unit. At most 250 per sale (`config/child-limits.php`).
The lines of a draft are replaced at each write and are never changed once the
sale is confirmed, except by the recalculation of the confirmation itself.
There is no VAT column and no discount. A line amount, and each of its
calculated amounts, may not exceed 18 digits, nor may a total exceed what a
`bigint` holds.

### `sale_fulfillments`, `sale_fulfillment_lines`

| Column | Meaning |
| --- | --- |
| `number` | `FL-{YEAR}-{SEQ}`, unique in the organization. |
| `status` | `draft`, `completed` or `cancelled`. |
| `inventory_transaction_id`, `reversal_transaction_id` | The inventory transaction that took the goods out, and the one that gave them back; empty when nothing tracked moved. |
| `completed_at`, `cancelled_at` | When the goods left, and when they came back. |

A line holds the sale line, the warehouse (`stock_location_id`, empty for an
item whose stock is not tracked), the quantity in the ratio-1 base unit like
the sale line, the lots asked for (`stock_ids`) and, once completed, what the
goods cost (`total_cost`, minor units of the functional currency). One line
per sale line and warehouse; at most 1 000 per fulfillment. It renders its
quantity in the unit sold in, with `unit_code`.

### `sale_holds`

What a bill-and-hold sale set aside at confirmation, one row per line and
warehouse: the quantity (base unit), the inventory transfer that moved it, and,
once the sale is cancelled, the quantity given back (`released_quantity`) and
the transfer that did it. What is still held for a line in a warehouse is the
quantity, less what was delivered from that warehouse, less what was given
back.

### `sale_transitions`

One row per gesture: the event (`create` included, from no status), the
status before and after, who acted (`member_id`, empty outside a request such
as a seeder, and emptied if the member is deleted), when, and the reason given
(required when rejecting, optional when abandoning or cancelling). A small
history beside the sale, until a global audit trail exists; the sale itself
keeps no "who" column. It has no limit per sale.

### On the customer

`customer_customers` gains `payment_due_date` (a date, or empty) and
`is_walk_in`. The walk-in customer serves counter sales: it is created the
first time the organization needs it, named "Walk-in customer" (an individual,
active), there is one per organization, and it cannot be deleted; it may be
renamed or deactivated, and an inactive one is refused like any customer.
`Lahatre\Customer\Contracts\CustomerInterface::walkInCustomer($organizationId)`
gives it to other modules, inside their transaction. It is safe when two
requests ask at the same moment.

The customer's `payment_due_date` is sent on create and update as `Y-m-d`, from
today on in the organization's timezone, or `null` to empty it; left out on an
update it stays as it is. The date is the one a credit sale falls back on
when it has none of its own (it is never copied, so moving it moves those
sales), and a past date stored on the customer no longer counts. It cannot be
emptied while credit sales follow it. `is_walk_in` is rendered and never accepted from a request. A
database index allows one walk-in customer per organization.

## Writing a draft

A draft is sent whole at each write: there is no call per line. Each write
recalculates every line and keeps the amounts.

```json
{
  "customer_id": "…",
  "mode": "bill_and_hold",
  "required_payment_percent": 30,
  "payment_due_date": "2026-12-31",
  "lines": [
    { "catalog_item_id": "…", "unit_code": "kg", "quantity": 3, "price_level_id": "…", "unit_amount": "1500" }
  ],
  "version": 4
}
```

- `customer_id` may be left out: the sale then goes to the walk-in customer.
  An inactive customer is refused.
- `mode` may be left out or sent empty: the default mode of the organization
  applies (`counter` when it set none). An unknown mode is refused by
  validation (422, on the field). `invoicing` and `delivery` cannot be sent.
- `required_payment_percent` is a whole number from 0 to 100. Left out or sent
  empty, it is 100. Below 100 it is a credit: it is refused for the walk-in
  customer (also when `customer_id` is left out), and it needs a due date
  later, at validation (see [Sale conditions](#sale-conditions)).
- `payment_due_date` is `Y-m-d`, today or later in the timezone of the
  organization; another format or an earlier day is refused by validation
  (422, on the field). It is kept as sent, also when the share is 100.
  Nothing is settled before validation.
- `lines` holds 1 to 250 lines, each item and unit once. An item must exist,
  be active, not be a service (services are not sold yet) and be sold in a
  unit of its unit group. A refusal names the item and the unit.
- Each line names its `price_level_id` and its `unit_amount`: the seller chooses
  the price, the system never does; it only checks what is sent. The level
  must be of the organization and not deleted (validation, on the field). When
  the customer has a price level of their own and the item has a price there
  for that unit, the line must be at that level; an item their level has no
  price for may be sold at any level. Every level must be in the functional
  currency, the only one taxes are calculated in, so a customer whose own
  level is in another currency cannot buy the items it prices. The item must
  have a price at the level for that unit; the way out is to set the price.
  `unit_amount` is the unit price read at that level, as the catalog keeps it
  (`catalog_item_prices.amount`, taxes it contains included, such as `"1500"`):
  if the price changed since it was read, the line is refused, so a sale is
  never written on a price the seller did not see. A seller cannot sell at
  another amount than the catalog's yet. It is the same name the line
  keeps and renders; the line's `amount` is that price times the quantity.
- `version` is required when replacing a draft and must be the one read.
  Only a `draft` is replaced; the replacement is made with the row locked.
- Ids that point nowhere in the organization (a deleted customer, a line item,
  unit code or price level) are refused by validation (422, on the field).

The catalog gives the sale what it sells through
`Lahatre\Catalog\Contracts\CatalogInterface::sellableItems($organizationId,
$requests)`: for a list of (item, unit, price level), the name, SKU, type,
activity, tax profile, unit, level name and currency, price and included taxes
of each, keyed by `{catalog_item_id}:{unit_code}:{price_level_id}`. The sale
asks for each line at its own level, and at the customer's level when they
have one. It only reads, leaves out an item that does not exist or is deleted,
and leaves the level and price empty for a level that does not exist; the
sale decides what to refuse. The number of queries is fixed. Taxes come from
`Lahatre\Tax\Contracts\TaxInterface::resolveMany()`, in one call for all
lines, in the functional currency. A write therefore costs the same number of
queries for 2 lines as for 250. A line's `quantity` is passed to the tax
calculation as it is.

## Calls

All under `/v1/sale`, for the current organization, all behind `auth.api`.
Answers go through [response selection](../api/response-selection.md). Like
other modules, a write answers `204` by default; with `?response=resource` it
returns the sale (`201` on creation, `200` otherwise), and `?include=` renders
its relations in that answer.

| Gesture | Call | Permission |
| --- | --- | --- |
| List | `GET sales` | `sale_sale.list` |
| Read | `GET sales/{sale}` | `sale_sale.retrieve` |
| Create a draft | `POST sales` | `sale_sale.create` |
| Replace the draft | `PUT sales/{sale}` | `sale_sale.update` |
| Validate | `POST sales/{sale}/validate` | `sale_sale.update` |
| Reopen | `POST sales/{sale}/reopen` | `sale_sale.update` |
| Abandon | `POST sales/{sale}/abandon` | `sale_sale.update` |
| Approve | `POST sales/{sale}/approve` | `sale_sale.approve` |
| Reject | `POST sales/{sale}/reject` | `sale_sale.approve` |
| Confirm | `POST sales/{sale}/confirm` | `sale_sale.confirm` |
| Cancel | `POST sales/{sale}/cancel` | `sale_sale.cancel` |
| Resume | `POST sales/{sale}/resume` | `sale_sale.cancel` |
| List the fulfillments | `GET sales/{sale}/fulfillments` | `sale_sale.retrieve` and `sale_fulfillment.list` |
| Read a fulfillment | `GET sales/{sale}/fulfillments/{fulfillment}` | `sale_sale.retrieve` and `sale_fulfillment.retrieve` |
| Create a draft fulfillment | `POST sales/{sale}/fulfillments` | `sale_sale.retrieve` and `sale_fulfillment.create` |
| Replace a draft fulfillment | `PUT sales/{sale}/fulfillments/{fulfillment}` | `sale_sale.retrieve` and `sale_fulfillment.update` |
| Delete a draft fulfillment | `DELETE sales/{sale}/fulfillments/{fulfillment}` | `sale_sale.retrieve` and `sale_fulfillment.delete` |
| Complete a fulfillment | `POST sales/{sale}/fulfillments/{fulfillment}/complete` | `sale_sale.retrieve` and `sale_fulfillment.complete` |
| Cancel a fulfillment | `POST sales/{sale}/fulfillments/{fulfillment}/cancel` | `sale_sale.retrieve` and `sale_fulfillment.cancel` |
| Read the settings | `GET settings` | `sale_setting.retrieve` |
| Set the default mode | `PUT settings` | `sale_setting.update` |

- `validate` takes `version` (required). `confirm` may take
  `keep_approved_prices` (a boolean, `false` when left out) and takes `lines`,
  the [sources](#sources) of the goods, for a counter or bill-and-hold sale. A
  fulfillment is created and replaced with `lines` (1 to 250). `reject`
  takes `reason` (required, up to 500 characters); `abandon`, `cancel` and
  `resume` may take one.
- Validating also needs the `approve` permission to go straight to
  `approved` when something needs an approval: the call itself needs only
  `update`, and the answer is `waiting_approval` when the caller may not
  approve. `confirm` needs `confirm` and works on an approved sale only, so
  it never needs `approve`.
- The list filters on `status` (one status or a list), `customer_id`,
  `number` (prefix), and `from_date` / `to_date` (`Y-m-d`, the days the sales
  were created, both included, in the organization's timezone), sorts by `created_at` or `number`, and pages with a
  cursor (`per_page` up to 100).
- The settings render no model, so both calls answer `200` with a body, and
  `?response=` has no effect on them. `PUT` takes `default_mode` (required,
  one of the three modes) and creates the row on the first write. Both answer
  `{"data": {"default_mode": "…", "modes": [...]}}`, where `modes` lists the
  three modes with their `code`, `invoicing` and `delivery`. A sale already
  written keeps its mode.
- A sale renders with the includes `lines`, `customer`, `transitions`,
  `fulfillments` and `holds`; a line with the include `price_level`
  (`include=lines.price_level`), a fulfillment with `lines`, and a fulfillment
  line with `stock_location`. A line shows its `delivered_quantity` and
  `left_to_deliver`, a hold its `held_quantity`.
  It shows `mode`, `invoicing` and `delivery` (the last two
  derived from the mode), `required_payment_percent`, `payment_due_date`,
  `due_date` (read only: the date that applies now, the sale's own else its
  customer's, empty for a sale paid at once; dates as `Y-m-d`) and
  `approval_reasons`.
  Amounts are decimal strings of the sale's currency. A line's `tax_result`,
  the kept calculation, renders with its amounts as decimals too
  (`TaxInterface::presentResult()`); it stays in minor units in the database.
- Lines and transitions have no permission of their own: they are reached
  through their sale. So are holds, and fulfillment lines through their
  fulfillment.
- A fulfillment is reached through its sale: one of another organization's
  sale answers 404, the sale itself 403.

## Permissions

`sale_setting` is generated like the others, but its policy opens only
`retrieve` and `update`: the settings are always those of the current
organization, so there is no list, create or delete. `sale_sale` has `list`, `retrieve`, `create`
and `update`, plus the additional actions `approve`, `confirm` and `cancel` (`resume` uses `cancel`).
`update` covers writing the draft and the gestures that move it back and forth
(validate, reopen, abandon); `approve` covers approving and
rejecting. A sale is never deleted: `sale_sale.delete` is generated but opens
nothing. `sale_fulfillment` has the five generated permissions, plus `complete`
and `cancel`; `delete` only ever removes a draft. `sale_line`,
`sale_transition`, `sale_fulfillment_line` and `sale_hold` are skipped by
permission discovery. See [IAM permissions](../iam/permissions.md).

## Refusals

Every business refusal is a `SaleException`, answered as a 422 with the
translated message. Each message gives its way out. A refusal met while confirming an approved sale adds that the sale is locked
and must be reopened to be changed.

| Situation | Way out |
| --- | --- |
| Customer deleted since, or sent but gone. | Choose another customer, or leave it out for the walk-in customer. At confirmation: reopen, then choose another. |
| Customer inactive, when the draft is written, validated, approved or confirmed. | Reactivate the customer, or sell to another one. |
| A line at another level than the customer's own, for an item priced there (named by SKU and unit). | Sell those items at the customer's level, or change the customer's level. |
| Price level of a line gone since (named by SKU and unit). | Choose another one. |
| Price level outside the functional currency. | Choose a level in the functional currency, or change the customer's level. |
| Items not in the organization (named by line position). | Remove those lines. |
| Items inactive. | Reactivate them, or remove them. |
| Services. | Remove them. |
| Unit outside the item's unit group. | Choose a unit of the group. |
| No price at the line's level in that unit (named by SKU, unit and level). | Set the price, or choose another level or unit. |
| The amount sent is not the price the catalog holds now (named by SKU, unit and level). | Read the prices again, then send the draft. |
| An item and unit on several lines. | Put each on one line. |
| A line amount or total too large. | Lower the quantity, or split the sale. |
| Replacing a sale that is not a draft. | Reopen it if it waits for approval, is rejected or approved, then change it. |
| Version differs from the one read. | Read it again, then send. |
| A gesture the status does not allow. | The message lists the gestures the status allows; none for a final status. |
| Share under 100 % (a credit) for the walk-in customer, when the draft is written, or at validation. | Choose a named customer, or require 100 % payment. |
| A credit with no due date: none on the sale, and the customer's is empty or past. | Type a due date on the sale, set one on the customer, or require 100 % payment. |
| The due date typed on the sale has passed since the draft was written (at validation). | Type a due date from today on the draft. |
| Emptying the customer's due date while credit sales follow it (waiting for approval, approved, active or cancelling, with no date of their own). | Keep a date on the customer and move it instead of emptying it; confirmed sales cannot be changed. |
| Confirming an approved credit whose due date that applies has passed. | The message adds that the sale is locked: reopen it, type a due date from today on, validate again. |
| At confirmation, a catalog price moved since the approval, at the line's level or at the customer's new level (names SKU, unit, level, approved and current price). | Confirm with `keep_approved_prices: true` to sell at the approved prices, or reopen the sale to take the new ones. |
| A source refused: a line not of the sale or listed twice, a warehouse missing, unknown, inactive or listed twice for a line, a lot not of that item in that location, sources not adding up to the whole line at confirmation, more than is left to deliver or still held. | A 422 on the field of the request, which says what fits. |
| Not enough stock in a warehouse, or in the lots named (names SKU, unit, warehouse, asked and available). | Take from another warehouse or other lots, or bring the stock in first. |
| Delivering a sale that is not `active`. | Only an active sale is delivered. |
| Changing, completing or deleting a fulfillment that is not a draft; cancelling one that is not completed. | Cancel a completed fulfillment instead of changing it. |
| Changing, deleting or transferring from a customer hold location (catalog). | Only the system writes it; change its warehouse instead. |
| Amounts changed since the calculation, by a tax (names SKU and unit code). | Reopen the sale, read it again, validate it, then confirm. |

The tax engine may also refuse with a `TaxException` when the taxes of a line
cannot be calculated; see the [tax module](tax.md).

## Left for later slices

- Charges. The amount is typed; a charge has a tax profile or none and goes
  through the same calculation as a line. A charge may be added to a confirmed
  sale: what that means once the single invoice of the modes that invoice at
  confirmation exists is settled then.
- Taxes chosen by context, such as Benin's AIB depending on the customer: a
  `tax_rules` layer selecting the profiles of a sale before calculation. Until
  then each line takes the profile of its item and keeps the profile used.
- Invoices, with document-level rounding (per invoice and rate) on top of the
  per-bracket rounding; then payments and their allocation, which let a
  fulfillment check the paid share before goods leave; then credit notes,
  returns (revocations), refunds and adjustments.
- A payment belongs to the customer who paid, not to a sale or an invoice.
  Allocations apply it to invoices: one payment may settle several invoices,
  one invoice may be settled by several payments, and what is left
  unallocated is an advance. The paid share of an invoice and the available
  amount of a payment are derived from the allocations, never kept as a
  balance of their own. Allocations exceed neither what the payment has
  available nor what the invoice still owes.
- Completing a fulfillment does not check the paid share yet: the goods of a
  credit leave as soon as it is completed. The check joins the completion once
  payments exist.
- Charges (delivery, transport) come from fulfillments, and returns start from
  a completed fulfillment (`Revocation` in `dossier-A3.pdf`).
- Who says what left: today the one who confirms a counter or bill-and-hold sale
  names the warehouses and lots. With a seller, a cashier and a storekeeper, the
  cashier does not know what the storekeeper chose to take out; closer to
  reality, the person who does the gesture says what actually happened.
- The item's `fulfillment_policy` of `dossier-A3.pdf`: today whether goods move
  stock follows from the inventory item's `stock_tracking_enabled`.
- Inventory locks, updates and records each movement's lots on its own, so a
  fulfillment's queries grow with its lines inside inventory, while the sale's
  own stay fixed.
- Corrections stay distinct: a credit note corrects an invoice, a return
  takes back delivered quantities, a refund is money actually given back,
  and reversing a payment voids a mistaken one without claiming a refund.
- Sales outside the functional currency, services, discounts. Before services
  are sold, master needs unit groups for time and count (hour, day, session):
  it has none today.
- A customer wallet with a credit limit set by the organization. Below that
  limit, a credit to the customer is granted without approval; going over
  the limit, or past the due date, is what needs an approval. Today every
  credit needs one.
- A confirmed sale of an inactive customer is still delivered, invoiced and
  paid, and credit notes, returns and refunds stay possible. Stopping the
  deliveries of a customer who does not pay is a separate, deliberate hold (a
  credit hold), to come with the customer wallet, not the inactive flag.
- A refusal the front end must turn into a question (prices moved since the
  approval) is recognized only by its message: `errors.type` is
  `SaleException` for every refusal and the context is hidden in production.
  A stable error code per refusal is to be decided.
- Giving up what remains to deliver closes a sale early; it then becomes
  `completed` by itself. A mark on the sale says a remainder was given up, and
  each line keeps the quantity given up; it is not a status. In the modes
  that invoice at confirmation that remainder is already invoiced, so giving it up goes
  through a credit note. Giving up everything when nothing was delivered is a
  cancellation, not an early close.
- In `progress_billing` a service must be delivered too, otherwise it
  is never invoiced.
- The payment methods an organization may use are a natural choice feature
  for plans.
