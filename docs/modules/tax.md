# Tax module

An organization defines its own taxes, groups them into tax profiles, and
assigns a profile to what it sells. The engine computes the taxes of a line
from that profile and returns an explainable result; it stores nothing. Routes
are listed in [the endpoint reference](../api/endpoints.md#tax).

## Amounts

Taxes are expressed in the organization's functional currency, which never
changes.

- **Definitions** (bracket bounds, fixed values, `tax_amount_min` and
  `tax_amount_max`, overrides) are decimals in units of the currency (`2.50`),
  with at most 6 decimals at the API. They may not have more decimals than the
  currency allows, except the rate of a per-unit tax (see below).
- **Calculations** work in minor units. A line's `base_amount` is a whole
  number of minor units (`250` for 2.50 EUR), and every amount in the result
  is a whole number of minor units written as a string. The engine compiles
  the definitions to minor units before calculating.
- Percentage values stay decimals: `18` means 18 %.

## Taxes

A tax has a unique `code` (upper case) and a `name` in the organization, a
`kind` (`vat` or `other`, default `other`), a calculation type, brackets,
optional dependencies, a `rounding_mode` and `is_active` (default true). A
create or update sends the complete definition: an update replaces the
brackets and the dependencies.

### Calculation types

- **`percentage`**: the bracket value is a rate applied to the taxable base.
- **`fixed`**: needs a `fixed_mode`.
  - `per_line`: the bracket value is an amount charged once per line,
    whatever the quantity.
  - `per_tax_unit`: the bracket value is a rate per unit of the tax's
    `tax_unit_code` (0.6069 EUR per litre, for example). The tax is charged on
    the sold quantity converted into that unit.

`fixed_mode` is refused on a percentage tax, and `tax_unit_code` is required
for `per_tax_unit` and refused otherwise.

### Brackets

Brackets must tile [0, ∞[ exactly, in payload order: the first starts at 0,
each starts where the previous one ends, only the last has no `base_max`, and
`base_max` is always greater than `base_min`. A bracket covers `base_min`
included up to `base_max` excluded, so a base equal to a bound falls in the
next bracket. Bounds apply to the taxable base amount, for every calculation
type.

- A non-progressive tax has exactly one bracket, [0, ∞[.
- A progressive tax (`is_progressive`) has at least two.
- Each bracket may set `tax_amount_min` and `tax_amount_max`; the minimum
  cannot exceed the maximum.

Sending a bracket's `id` on update keeps its identity, so profile overrides
stay attached to it. A bracket left out is deleted, unless a profile overrides
it: that update is refused.

### Bracket modes

A progressive percentage tax needs a `bracket_mode`; every other tax refuses
one.

- **`whole_amount`**: the rate of the bracket the whole base falls in applies
  to the whole base.
- **`marginal`**: each bracket's rate applies only to the portion of the base
  inside it, and the tax is the sum of the portions' taxes.

A progressive fixed tax has no mode: it charges the value of the single
bracket the base falls in.

### Per-unit precision

A per-unit rate may be finer than the currency: 0.6069 EUR per litre is
accepted in an EUR organization, and is compiled to 60.69 cents, keeping its
decimals. The charge is rate × sold quantity × sale-unit ratio, divided by the
tax-unit ratio last, so the unit conversion loses no precision. Only the
charged amount is rounded. A `per_line` value, the amount limits and the
bounds must still fit the currency precision.

### Rounding

Per bracket, the engine computes the raw amount, raises it to
`tax_amount_min` unless it is zero (nothing to tax stays untaxed, in every
mode), lowers it to `tax_amount_max`, then rounds it to a whole
minor unit with the tax's `rounding_mode`: `half_up` (default, away from
zero), `up` or `down`. A tax amount is the sum of its rounded brackets, and
the line total is the sum of the tax amounts. Document-level rounding does
not exist yet.

## Dependencies

A tax can depend on other taxes of the organization, with the operator `add`
or `subtract`: the dependency's tax amount on the same line is added to, or
subtracted from, the taxable base. Its `base_type` says what the base starts
from:

- **`amount_with_dependencies`** (default): the line amount, plus or minus the
  dependencies. A dependency missing from the profile contributes nothing.
- **`dependencies_only`**: the dependencies alone. Such a tax needs at least
  one `add` dependency, and every profile using it must contain all of its
  dependencies.

Saving refuses a tax depending on itself, the same dependency twice, a
dependency from another organization, and any cycle; the error names the
codes of the cycle. The engine computes each dependency before its dependents,
breaking ties by code. A base that dependencies make negative fails the
calculation with `negative_taxable_base`.

## Tax profiles

A profile is a set of taxes applied together, with a unique `code` and `name`
and `is_active`. It has 1 to 20 lines, each naming a tax once with a
treatment:

- **`standard`**: the tax as defined.
- **`custom`**: the tax with `bracket_overrides`, at least one. An override
  replaces the `value` of one of the tax's own brackets, at most once per
  bracket; bounds and amount limits stay those of the tax. Overrides follow
  the same precision rule as the tax.
- **`zero`** and **`exempt`**: the tax appears in the result with an amount
  of 0. The two treatments differ only in what they record.

Overrides are refused on any other treatment. A profile holds at most one
`vat` tax, and an active profile cannot contain an inactive tax. An update
replaces the lines and their overrides.

Changing a tax must keep the profiles using it valid. While a profile uses a
tax, its calculation type, fixed mode and tax-unit group cannot change, it
cannot become a second VAT in a profile, it cannot be deactivated under an
active profile, and a dependencies-only tax keeps all its dependencies in each
profile. A tax used by a profile, or that another tax depends on, cannot be
deleted. Deleting a tax or a profile is a soft delete.

## Catalog items

`catalog_items.tax_profile_id` assigns a profile to a variant, a bundle or a
service; `null` means the item is not taxed. Assigning checks, inside the
item's transaction, that the profile exists in the organization, is not
deleted, is active, and that each applicable per-unit tax uses a unit of the
item's unit group.

The catalog implements `TaxProfileUsageInterface`, which gives the unit groups
of the non-deleted items using each profile; without catalog,
`NoTaxProfileUsage` reports no usage. A profile used by an item cannot be
deactivated or deleted (`profile_in_use`), and an update cannot add an
applicable per-unit tax whose unit belongs to another group than an item using
the profile (`tax_unit_incompatible`). A soft-deleted item no longer counts.

## Calculation preview

`POST /v1/tax/profiles/{profile}/calculations` calculates one line on a
profile while it is being configured: `base_amount` in minor units, a positive
`quantity`, and the sale `unit_code` when the profile has an applicable
per-unit tax. The unit group comes from that sale unit. Unlike the
`TaxInterface`, the preview accepts an inactive profile and inactive taxes, so
a draft can be checked before it is switched on. It reads the database, not
the cache, and returns the same result as `resolve()`.

## Limits

`config/child-limits.php` caps the children of a record; PostgreSQL enforces
them and the requests refuse larger arrays: 50 brackets and 20 dependencies
per tax, 20 lines per profile, 50 bracket overrides per profile line.

## Locks and cache

Every configuration write (taxes and profiles, create, update, delete) takes
the exclusive transaction lock `tax-configuration:<organization>` through
`TaxConfigurationLock`, then locks the written row. Cross-row rules, such as
cycles and profile completeness, are therefore checked against a stable state.
Readers take the same key in shared mode: the compiler reads a profile and
its taxes in one transaction under it, and `assertProfileAssignable()` takes
it in the caller's transaction, so an assignment and a concurrent
deactivation cannot both succeed. Both go through `TransactionLock`, which
gives up after 5 seconds and refuses to run outside a transaction.

Compiled profiles are cached in request memory, then in the application cache.
Their keys carry a per-organization version token that each write replaces
after commit, so a stale entry is never read once the change is visible.

## The `TaxInterface` contract

Other modules use `Lahatre\Tax\Contracts\TaxInterface`, for the current
organization:

- `assertProfileAssignable($taxProfileId, $unitGroupIds)`: the check described
  under catalog items. Call it inside the assigning transaction.
- `resolve(TaxableLineData $line)` and `resolveMany($lines)`: calculate lines
  with a fixed number of queries whatever their count. The result collection
  is keyed like the input.

A `TaxableLineData` carries `tax_profile_id` (a line without one has no tax),
the item's `unit_group_id`, `base_amount` in minor units after discounts,
`currency_code` (it must be the functional currency), the sold `quantity` (a
positive decimal, up to 10 decimals) and the sale `unit_code`, required when an
applicable per-unit tax exists and taken from the item's unit group.
Resolution refuses an inactive profile or a profile holding an inactive tax.

`TaxResultViewData` explains each tax: its settings and treatment, the input
base, the dependency amount and taxable base, the quantity in the tax unit,
each bracket with its taxable portion and whether its value was overridden,
the dependencies used, and the tax amount. Nothing is persisted: a document
freezes `toArray()` (`schema_version` 1) when it is confirmed.

Every business refusal is a `TaxException`, answered as a 422 with the
translated message and, outside production, a context naming the codes or
fields involved.

## Permissions

Taxes use `tax_tax.*` and profiles `tax_profile.*`. Brackets and dependencies
are managed through their tax, lines and overrides through their profile; they
have no permission of their own. The preview needs `tax_profile.retrieve`. See
[IAM permissions](../iam/permissions.md).

## Open questions

- See the [tax follow-ups](../roadmap.md#todo-tax-follow-ups) for tax
  snapshots, tax-inclusive prices, document-level rounding and taxes chosen
  by context.
