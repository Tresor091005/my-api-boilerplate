# Business exceptions

Business exceptions extend `Lahatre\\Shared\\Exceptions\\AssertionException`.
They are reserved for invariants that cannot be expressed correctly through a
`FormRequest`, validation rule, or database constraint.

## One class per model

When several rules concern the same model, group them in one exception named
`<Model>Exception`. Expose each rule through a descriptive static method:

```php
throw CategoryException::hasChildren($category);
throw CategoryException::cannotBeDescendantParent($category, $parentId);
```

This organization provides a readable catalog of model invariants and avoids
creating multiple files for variants of the same domain.

Static methods must build the translated message with `__()` and keep useful
identifiers in `context()`.

## When to keep a separate exception

Keep an exception in its own file when it describes a workflow or
infrastructure rather than a single model. Examples include the current
organization, transaction reversal, idempotency, insufficient stock, and
authentication failure.

Cross-cutting exceptions must not be artificially attached to a model when the
rule concerns several objects or a process step.

## Current catalog

- `catalog`: `CategoryException`, `OptionException`, `OptionValueException`,
  `ProductVariantException`.
- `master`: `UnitException`, `TagException`.
- `pricing`: resolution and validation errors remain grouped by responsibility
  because they concern several contracts or polymorphic targets.
- `inventory`: transaction, stock, organization, and idempotency errors remain
  separate because they concern workflows or several models.
- `iam`: authentication errors remain separate from the user model.

Tests may inspect the message or context to distinguish the precise rule within
a model exception.
