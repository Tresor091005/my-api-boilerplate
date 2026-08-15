# Decision: separating Form Requests and Data classes

## Problem resolved

`LahatreDTO` validated, normalized, cast, and built transport objects in one
abstraction. Nested DTOs also created their own validators, which lost the
parent error paths such as `units.0.name` and `variants.1.options.0.value`.

## Applied decision

- Form Requests are the HTTP validation boundary and validate nested arrays
  with Laravel's `field.*` rules.
- Data classes are immutable transport objects built through `::fromArray()`
  after validation.
- Controllers retain Gates and Policies.
- Services consume Data objects and explicitly map them to models.
- `MissingValue` distinguishes an absent key from an explicit `null`, `false`,
  `0`, empty string, or empty array.

## Migration status

The IAM, Catalog, Master, and Inventory modules have been migrated. The
`LahatreDTO` class, its casts, concerns, Artisan command, stubs, and dedicated
tests have been removed.

The complete convention is described in [Form Requests and Data classes](../architecture/data/form-requests-and-data.md) and the [central rules](../../.agents/CODEBASE_RULES.md).
