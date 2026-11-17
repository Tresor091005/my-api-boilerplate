# Tax module

An organization defines its own taxes, groups them into tax profiles, and
assigns a profile to what it sells. The engine computes the taxes of a line
from that profile and returns an explainable result; it stores nothing. Routes
are listed in [the endpoint reference](../api/endpoints.md#tax).

## Amounts

Taxes are expressed in the organization's functional currency, which never
changes.

- **Definitions** (bracket bounds, fixed values, `tax_amount_min` and
  `tax_amount_max`, custom grids) are decimals in units of the currency (`2.50`),
  with at most 6 decimals at the API. They may not have more decimals than the
  currency allows, except the rate of a per-unit tax (see below).
- **Calculations** work in minor units. Inside the application a line's
  `amount` is a whole number of minor units (`250` for 2.50 EUR), and every
  amount in the result is a whole number of minor units written as a string;
  that is also the form a document freezes. The engine compiles the
  definitions to minor units before calculating. The API speaks decimals:
  the simulation takes and answers decimal amounts, and
  `TaxInterface::presentResult()` renders a kept result with its amounts as
  decimals of its currency for any module that shows one.
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
  cannot exceed the maximum. A `per_line` tax cannot set them: its amount is
  already fixed, so a limit would only replace it.

An update replaces the tax's own grid. The custom grids of profile lines are
separate and stay as they are.

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
- **`custom`**: the tax with its own grid in `brackets`: bounds, values and
  amount limits. The grid replaces the tax's grid completely; nothing is
  inherited from it. It keeps the shape of the tax (calculation type, fixed
  mode, bracket mode, progressive or not) and follows the same bracket rules
  as the tax's own grid. Custom brackets are stored in `tax_brackets` with
  their `tax_profile_line_id`.
- **`zero`** and **`exempt`**: the tax appears in the result with an amount
  of 0. The two treatments differ only in what they record.

A grid is refused on any other treatment. A profile holds at most one
`vat` tax, and an active profile cannot contain an inactive tax. An update
replaces the lines and their custom grids.

Changing a tax must keep the profiles using it valid. While a profile uses a
tax, its calculation type, fixed mode and tax-unit group cannot change; while
a profile gives it a custom grid, it stays progressive or not; it
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

A module whose records reference profiles implements
`TaxProfileUsageInterface`, which gives the unit groups of its non-deleted
records using each profile and the taxes their amounts already contain, and
tags its implementation with
`TaxProfileUsageInterface::SOURCES_TAG` in its provider's `boot()`. The tax
module asks every tagged source and merges their answers
(`CombinedTaxProfileUsage`), so a new source never replaces another one;
without a source, no profile is used. The catalog is the only source today. A profile used by an item cannot be
deactivated or deleted (`profile_in_use`), and an update cannot add an
applicable per-unit tax whose unit belongs to another group than an item using
the profile (`tax_unit_incompatible`). A soft-deleted item no longer counts.

A price of an item may state that its amount already contains some taxes of
the item's profile (`catalog_item_price_taxes` in the catalog). Such a price
must stay [an amount its taxes can be taken out of](#amounts-that-already-contain-taxes),
so a sale never meets a price it cannot calculate. Three moments keep it true,
each refusing where the change is made:

- **Writing the price**, or changing its amount or its taxes: each tax is a
  line of the item's profile (`included_taxes_outside_profile`), the taxes can
  be contained together, and the price is calculated once for one unit sold,
  so it is not lower than the fixed taxes it contains.
- **Giving the item another profile, or none**: the same checks run against
  the new profile for every price of the item that contains taxes.
- **Changing a tax or a profile**: the catalog also reports, through
  `TaxProfileUsageInterface`, the distinct sets of taxes the prices of items
  using each profile contain. After its writes, the tax or profile update
  checks the profiles it changed against those sets and is refused
  (`included_taxes_in_use`, naming the tax and the reason) when a tax could no
  longer be contained: a new minimum, a rate now applied to the whole amount, a
  dependency on a tax the prices leave out.

The way out of each refusal is to change the prices first: the taxes a price
contains can be changed on the price, and a price before tax contains none. A
tax that leaves a profile is simply no longer contained. A new rate or a new
fixed amount is never refused: the prices stay what they are and their amount
before tax follows. Only a fixed tax raised above a price it is contained in
is met later, when that price is sold or changed
(`amount_below_included_taxes`).

## Amounts that already contain taxes

A line may state that its amount already contains some taxes of its profile,
in `included_tax_ids`: a price displayed with its value-added tax, for example.
The engine then finds the base the amount stands for, calculates the contained
taxes on it, and adds the other taxes of the profile on top.

- The amount is kept to the unit: `amount = base_amount + included_tax_amount`
  and `total_amount = amount + added_tax_amount`.
- When rounding leaves no whole base that gives the amount back, one of the two
  nearest bases is kept and one contained tax takes the difference, in its
  `rounding_adjustment`. That tax is then its exact value rounded with its own
  rounding mode: `amount × rate / (100 + rate)` for a single rate. 121 at 18 %
  is 102.54 before tax and 18.46 of tax, kept as 103 and 18. The value-added
  tax takes the difference when the amount contains it, otherwise the last
  rate calculated; a fixed amount never does.
- The base is found for the whole line, with its real quantity: a per-unit tax
  and the rounding of a line do not scale with a unit price.
- A tax added on top may be calculated on a contained tax; it sees the amount
  kept for that tax.
- A contained tax that is not in the profile is left out, so a price stays
  usable once its tax has left the profile.

Only a tax that grows smoothly with its base can be taken out of an amount;
otherwise some amounts would have no base at all. An amount is refused when a
contained tax that applies:

| Refusal | The tax |
| --- | --- |
| `included_tax_whole_amount` | applies its rate to the whole amount past a threshold |
| `included_tax_fixed_by_bracket` | is a fixed amount that changes from one bracket to the next |
| `included_tax_has_minimum` | has a minimum amount |
| `included_tax_subtracts_dependency` | subtracts another tax from its base |
| `included_tax_depends_on_added_tax` | is calculated on a tax the amount does not contain |

The amount is also refused when it is lower than the fixed taxes it contains
(`amount_below_included_taxes`). A refused tax can always be left out of the
amount and added on top. A zero-rated or exempt tax is contained for nothing
and is never refused.

## Simulation

`POST /v1/tax/simulations` calculates lines without recording anything: for
whoever configures a profile and wants to check it, and for whoever prepares a
sale and wants its totals. Each line names its `tax_profile_id`, with
`amount` as a decimal of the functional currency, the taxes that amount
already contains in `included_tax_ids` if any, a positive `quantity`, and the
sale `unit_code` when the profile has an applicable per-unit tax. The answer
gives each line's result, as `resolve()` returns it but with its amounts as
decimals, the total tax amount and the total amount to pay.

The simulation applies the rules of `TaxInterface`: an inactive profile or tax
is refused. With `include_inactive: true`, they are calculated too, so a draft
can be checked before it is switched on; only a member who may also update
profiles can ask for it.

## Limits

`config/child-limits.php` caps the children of a record; PostgreSQL enforces
them and the requests refuse larger arrays: 50 brackets and 20 dependencies
per tax, 20 lines per profile, 50 brackets in the custom grid of a profile
line.

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
- `assertTaxesIncludable($taxProfileId, $amounts)`: the check of amounts that
  already contain taxes, such as prices, each given with its taxes and sale
  unit (`IncludedTaxAmountData`). Call it inside the transaction writing the
  amounts or assigning the profile.
- `assertProfileAssignableWithIncludedTaxes($taxProfileId, $unitGroupIds, $amounts)`:
  both checks with one read of the profile, for a record whose amounts
  already contain taxes and which is given a profile.
- `resolve(TaxableLineData $line)` and `resolveMany($lines)`: calculate lines
  with a fixed number of queries whatever their count. The result collection
  is keyed like the input.

A `TaxableLineData` carries `tax_profile_id` (a line without one has no tax),
`amount` in minor units after discounts, `included_tax_ids` (the taxes that
amount [already contains](#amounts-that-already-contain-taxes), none by
default), `currency_code` (it must be the functional currency), the sold
`quantity` (a positive decimal, up to 10 decimals) and the sale `unit_code`,
required when an applicable per-unit tax exists. The unit of each per-unit tax must belong to the group of the sale
unit. Whether the sale unit suits the item sold is the caller's rule, not the
tax module's: the engine applies a profile to an amount and a quantity.
Resolution refuses an inactive profile or a profile holding an inactive tax.

`TaxResultViewData` gives the `amount` of the line, its `base_amount` before
any tax, `included_tax_amount`, `added_tax_amount`, `total_tax_amount` and
`total_amount`. It explains each tax: its settings and treatment, whether the
amount contained it (`is_included`), the input base, the dependency amount and
taxable base, the quantity in the tax unit, each bracket of the grid used with
its taxable portion, the dependencies used, its `rounding_adjustment` and the
tax amount. Nothing is persisted: a document freezes `toArray()`
(`schema_version` 2) when it is confirmed.

Every business refusal is a `TaxException`, answered as a 422 with the
translated message and, outside production, a context naming the codes or
fields involved.

## Permissions

Taxes use `tax_tax.*` and profiles `tax_profile.*`. Brackets and dependencies
are managed through their tax, lines and custom grids through their profile; they
have no permission of their own. The preview needs `tax_profile.retrieve`. See
[IAM permissions](../iam/permissions.md).

## Open questions

- See the [tax follow-ups](../roadmap.md#todo-tax-follow-ups) for country
  packs, and the [next steps](../roadmap.md#next-steps) for tax snapshots,
  document-level rounding and taxes chosen by context.
