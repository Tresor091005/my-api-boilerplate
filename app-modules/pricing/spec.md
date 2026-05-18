# Pricing Module Spec

Goal: introduce a simple contextual price list system for ERP flows. This is not a full pricing rule engine. The module stores applicable prices for priceable elements and lets business flows resolve or validate a price based on explicit context.

Date: 2026-05-17

## 1) Principles

- A price entry is a contextual price, not a computed discount or promotion rule.
- A price can target a precise element or a pricing group of elements.
- A price can target a precise actor, a pricing group of actors, or no actor.
- The resolver returns applicable price entries sorted from best to least specific.
- Validation checks whether a chosen amount is allowed for the current case.
- Discounts, coupons, promotions, advanced tax logic, and margin enforcement are outside v1.
- Tax calculation should remain a separate concern, even if the selling flow eventually combines price resolution and tax resolution.

## 2) Vocabulary

`priceable`
- The thing being priced.
- Examples: product variant, product, service, bundle, delivery fee.

`priceable_group`
- A pricing-specific group of priceable elements.
- It must not be inferred directly from navigation tags, product categories, brands, or UI classifications.
- Those classifications may be used by an application workflow to populate or sync pricing groups, but the pricing resolver reads only pricing groups.

`party`
- The actor for whom or with whom the price applies.
- Examples: customer, supplier, organization, partner.

`party_group`
- A pricing-specific group of actors for which a price is valid.
- Examples: wholesale customers, local suppliers, contract customers.

`context`
- The business use case for the price.
- Examples: `selling`, `buying`.

## 3) Model Eligibility

Pricing must not accept any random morph target silently. A model must explicitly opt in before it can be used as a priceable element or as a pricing party.

Recommended contracts:

`HasPriceable`
- Marks a model as something that may have price entries.
- Examples: product variant, product, service, bundle, delivery fee.
- Provides the compatible unit group and the default pricing unit when quantity boundaries need validation or conversion.
- Provides a stable pricing label or summary for API resources and debugging.

`HasPricingParty`
- Marks a model as an actor that may be used on the party side of a price entry.
- Examples: customer, supplier, partner, organization.
- Provides a stable pricing label or summary for API resources and debugging.

Recommended group contracts:

`HasPriceableGroup`
- Marks a model as a pricing group of priceable elements.
- In v1 this should usually be implemented only by `PriceableGroup`.

`HasPricingPartyGroup`
- Marks a model as a pricing group of actors.
- In v1 this should usually be implemented only by `PartyGroup`.

This is related to selling and buying, but it is not exactly the same responsibility:

- `HasPriceable` means the model can be priced.
- `context = selling` means this price entry is valid for a sale.
- `context = buying` means this price entry is valid for a purchase.

A product variant may be both sellable and buyable, but the pricing module should not need separate `Sellable` and `Buyable` contracts for v1. Context is enough to distinguish sale prices from purchase prices. If later the domain needs stronger rules, dedicated business contracts such as `Sellable` or `Purchasable` can be added by sales or purchase modules and checked before calling pricing.

## 4) Scope Matching

A price entry has two matching sides:

- `priceable`: precise item or priceable group.
- `party`: precise actor, party group, or null.

The resolver must evaluate candidates in this natural order:

1. Precise item + precise party.
2. Precise item + party group.
3. Precise item + party null.
4. Priceable group + precise party.
5. Priceable group + party group.
6. Priceable group + party null.
7. No candidate.

Other fields are filters, not specificity dimensions:

- organization
- context
- currency
- unit
- quantity boundaries
- active period
- active status

## 5) Data Model

### 5.1 Table `pricing_price_entries`

Purpose: store contextual prices that can be resolved or validated by business flows.

Proposed fields:

- `id` uuid primary key
- `organization_id` uuid required
- `priceable_type` string
- `priceable_id` uuid
- `priceable_kind` enum: `item`, `group`
- `party_type` string nullable
- `party_id` uuid nullable
- `party_kind` enum nullable: `actor`, `group`
- `context` string
- `currency_code` char(3)
- `unit_code` string
- `min_quantity` decimal or bigint
- `max_quantity` decimal or bigint nullable
- `amount` bigint
- `starts_at` timestamp nullable
- `ends_at` timestamp nullable
- `is_active` boolean default true
- `metadata` jsonb nullable
- timestamps
- soft deletes

Notes:

- `amount` should use minor currency units.
- `unit_code` references the master unit code and represents the commercial/display unit of the price entry.
- `min_quantity` and `max_quantity` belong to pricing, not to master units.
- Units describe conversion. Price entries describe when a price applies.
- `min_quantity` and `max_quantity` should be stored in the normalized base quantity used for database integrity.
- The resolver and validator may receive quantities in another compatible unit, but they must convert them before comparing with stored boundaries.
- For v1, a given item priceable should use one pricing unit strategy consistently. Mixing commercial units such as `kg` and `unit` for the same priceable is not supported.

Constraints:

- `priceable_kind = item` means `priceable_type/id` points to a real priced element.
- `priceable_kind = group` means `priceable_type/id` points to a pricing priceable group.
- `party_kind = actor` means `party_type/id` points to a real actor.
- `party_kind = group` means `party_type/id` points to a pricing party group.
- `party_type`, `party_id`, and `party_kind` are all nullable together for a public/default price.
- `min_quantity` must be greater than or equal to zero.
- `max_quantity` must be null or greater than or equal to `min_quantity`.
- `ends_at` must be null or greater than or equal to `starts_at`.
- `unit_code` must belong to the compatible unit family exposed by the targeted priceable.

Recommended indexes:

- `(organization_id, context, currency_code, is_active)`
- `(priceable_type, priceable_id, priceable_kind)`
- `(party_type, party_id, party_kind)`
- `(unit_code)`
- `(starts_at, ends_at)`

Uniqueness rule for v1:

- A price entry must be unique by resolution scope, not by amount.
- Two active entries must not describe the exact same case for the same `organization_id`, `priceable` target, `party` target, `context`, `currency_code`, `unit_code`, quantity range, and active period.
- Because overlap detection is hard to guarantee with a simple database unique index, v1 may enforce this rule at the application service level.

### 5.2 Table `pricing_priceable_groups`

Purpose: define pricing-specific groups of priceable elements.

Proposed fields:

- `id` uuid primary key
- `organization_id` uuid required
- `name` string
- `description` text nullable
- `is_active` boolean default true
- `metadata` jsonb nullable
- timestamps
- soft deletes

### 5.3 Table `pricing_priceable_group_members`

Purpose: attach priceable elements to pricing groups.

Proposed fields:

- `id` uuid primary key
- `group_id` uuid foreign key to `pricing_priceable_groups.id`
- `priceable_type` string
- `priceable_id` uuid
- timestamps

Recommended constraints:

- Unique `(group_id, priceable_type, priceable_id)`.
- Index `(priceable_type, priceable_id)`.

### 5.4 Table `pricing_party_groups`

Purpose: define pricing-specific groups of actors.

Proposed fields:

- `id` uuid primary key
- `organization_id` uuid required
- `name` string
- `description` text nullable
- `is_active` boolean default true
- `metadata` jsonb nullable
- timestamps
- soft deletes

### 5.5 Table `pricing_party_group_members`

Purpose: attach actors to pricing groups.

Proposed fields:

- `id` uuid primary key
- `group_id` uuid foreign key to `pricing_party_groups.id`
- `party_type` string
- `party_id` uuid
- timestamps

Recommended constraints:

- Unique `(group_id, party_type, party_id)`.
- Index `(party_type, party_id)`.

## 6) Resolver

### 6.1 Resolve Applicable Prices

Input:

- organization
- priceable model
- party model nullable
- context
- currency code
- unit code
- quantity
- date

Output:

- A collection of applicable `PriceEntry` records sorted from best to least preferred.
- The collection may be empty.

Filtering rules:

- Price entry is active.
- Organization matches.
- Context matches.
- Currency matches.
- Date is inside `starts_at` and `ends_at` when boundaries exist.
- Quantity is inside `min_quantity` and `max_quantity` when boundaries exist.
- Unit matches or is compatible through the master unit conversion strategy chosen by the implementation.
- Priceable matches the precise item or one of its pricing groups.
- Party matches the precise actor, one of its pricing groups, or null.

Sorting rules:

1. Precise priceable before priceable group.
2. Precise party before party group.
3. Party group before party null.
4. Highest `min_quantity`.
5. Most recent `starts_at`.
6. Most recent `created_at`.
7. Stable tie-breaker by `id`.

Rationale:

- Higher `min_quantity` usually represents a more specific quantity tier.

### 6.2 Validate Chosen Amount

Input:

- amount
- organization
- priceable model
- party model nullable
- context
- currency code
- unit code
- quantity
- date
- user or actor performing the operation
- optional bypass reason

The validator must verify that the chosen amount appears in the applicable price collection for the given case.

This deliberately validates by `amount`, not only by `price_entry_id`, because business records such as quote lines, sales order lines, invoices, purchase order lines, and receipts often need to debug the visible price that was applied. A `price_entry_id` may still be stored as trace metadata when available, but the amount remains the core validation input.

The validator may accept a decimal amount from the caller, but it must convert that amount into the stored minor-unit representation before comparison.

Validation outcomes:

- If at least one applicable price entry has the chosen amount, the amount is valid.
- If no applicable price entry has the chosen amount and the user has no bypass permission, validation fails.
- If no applicable price entry has the chosen amount and the user has bypass permission, validation may pass only with an auditable bypass reason.

Suggested audit metadata for bypass:

- `pricing_bypassed` boolean
- `pricing_bypass_reason`
- `pricing_bypassed_by`
- `pricing_bypassed_at`
- `expected_amounts`
- `chosen_amount`

Important distinction:

- `applicable` means the amount can be used for this case.
- `best` means the resolver would return it first.

The validator should not require the chosen amount to be the first resolved price unless a separate business policy explicitly enforces best-price usage.

## 7) Business Examples

### 7.1 Public Selling Price

Tomato Roma has a public selling price:

- priceable: Tomato Roma
- party: null
- context: `selling`
- currency: `XOF`
- unit: `kg`
- min quantity: `1`
- amount: `1000`

Any customer buying Tomato Roma in this context may use `1000 XOF/kg` unless a more specific price is selected.

### 7.2 Wholesale Customer Price

Tomato Roma has a wholesale selling price:

- priceable: Tomato Roma
- party: Wholesale Customers pricing group
- context: `selling`
- currency: `XOF`
- unit: `kg`
- min quantity: `50`
- amount: `850`

For a wholesale customer buying `80 kg`, both public and wholesale prices may be applicable. The resolver returns the wholesale price first because the party match is more specific and the quantity tier is higher.

### 7.3 Priceable Group Price

The pricing group Green Vegetables has a fallback selling price:

- priceable: Green Vegetables pricing group
- party: null
- context: `selling`
- currency: `XOF`
- unit: `kg`
- min quantity: `1`
- amount: `700`

If Tomato Roma belongs to Green Vegetables and has no better direct price, this group price may be used.

### 7.4 Supplier Purchase Price

Tomato Roma has a purchase price for Local Suppliers:

- priceable: Tomato Roma
- party: Local Suppliers pricing group
- context: `buying`
- currency: `XOF`
- unit: `kg`
- min quantity: `1`
- amount: `600`

This price is valid for buying from a supplier in the Local Suppliers pricing group. It is not valid for selling.

## 8) Implementation Decisions

- `organization_id` is required in pricing tables.
- Quantity thresholds are stored in the normalized base quantity for integrity. Input quantities may use another compatible unit, but must be converted before matching.
- `unit_code` identifies the commercial/display unit of the entry and must stay consistent with the priceable unit strategy.
- Validation may accept decimal user input amounts, but comparison is performed against the stored minor-unit integer representation.
- `price_entry_id` may be stored as optional trace metadata on business records even though validation is amount-based.
- Once a business record is committed, the stored chosen amount is the authoritative historical value. Later changes in price groups or party groups do not retroactively invalidate that history.
