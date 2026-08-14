---
paths:
  - app-modules/*/src/Rules/**
  - app-modules/*/src/Validation/**
  - app-modules/*/src/Http/Requests/**
---

# Composable Validation

## Choose the Correct Mechanism

- Keep scalar types, formats, presence, and simple conditional fields directly in the Form Request.
- Extract a `ValidationRule` when a named reusable check, a bulk SQL lookup, a nested structure, or a large cross-field check would obscure the Request schema.
- Use a model-centered Assertion when the rule must protect persisted business state regardless of whether the caller is HTTP, a job, or a command.
- Reserve `src/Validation` for exceptional cohesive domain validators that coordinate several validation phases and bulk lookups and cannot remain understandable as Assertions plus composable Rules. Call the validator explicitly from a Form Request validation hook or from a service input boundary when the same input contract is used outside HTTP.
- Do not move validation into `src/Validation` merely because a Form Request is long; first use ordinary rules, a composable Rule, or a focused `withValidator()`/`after()` callback.

## FormRequest Contract

- Keep `rules()` readable as the input schema: scalar types, formats, presence, simple conditionals, `exists`, `unique`, enum membership, and small field relationships belong there.
- Do not use `sometimes` by default. An omitted field needs no rule; an accepted `null` uses `nullable`; a required key that may be empty uses `present`; a required non-empty value uses `required`.
- Use `present|array` when the array key must exist, including when an empty array is valid. `min:0` adds no presence semantics.
- `prohibited`, `prohibited_if`, and `prohibited_unless` are allowed for conditional payloads. Treat wildcard combinations carefully; if the behavior depends on parent or sibling wildcard data, move the orchestration to `withValidator()`/`after()` and test the exact paths.
- A Form Request may serve create and update when their payload shape is coherent. Merge rules explicitly using the route model or action context; do not hide materially different contracts behind dense conditionals.
- Use `prepareForValidation()` only for named normalization that the endpoint contract requires, such as trimming a specific field or normalizing case. Never recursively normalize every input value.

## Custom Rule Dependency Discipline

- Prefer a plain `ValidationRule` whose dependencies are visible in its constructor or whose value is self-contained.
- `DataAwareRule` is exceptional because it lets a rule attached to one field depend on sibling or parent payload data that the caller may not see. Before using it, consider attaching the rule to the dependent structure, passing explicit constructor data, or using `withValidator()`/`after()`.
- When `DataAwareRule` is unavoidable, document the exact fields it reads, return safely when those fields are absent or structurally invalid, and test both correct and incorrect placement/use.
- Use `ValidatorAwareRule` when the important contract is precise nested error paths, several related failures, or validator state. Do not use it merely as a convenient way to access the payload.
- Custom Rules must not authorize, resolve permissions, call Policies or Gates, mutate persistence, start transactions, or throw business exceptions.

## Database-Aware Validation

- Before writing `exists`, `unique`, or a database-aware Rule, inspect the target model and migration for `organization_id`, `deleted_at`, `is_active`, `status`, parent ownership, and the actual business uniqueness condition.
- Tenant, soft-delete, active-state, status, and parent constraints must be explicit whenever the validation contract requires them. A bare `exists:table,id` is not sufficient when the model has additional authenticity boundaries.
- Never put an `exists` or `unique` query inside a loop. That is an N+1 validation query pattern disguised as iteration. Collect unique identifiers, query once, key the result, and compare in memory.
- Database-aware validation follows the global persistence rule: bulk queries, explicit tenant and soft-delete boundaries, and no avoidable query or lazy load in a loop.
- SQL efficiency must not reduce validation error fidelity. A bulk Rule must
  report every invalid input element at its original index, especially for
  nested payloads, instead of failing once on the parent array. Use
  `ValidatorAwareRule` when necessary to add errors such as
  `variants.0.sku` and `variants.2.sku` while retaining the single bulk query.
- A validation check answers whether input is acceptable now; persistent business invariants still belong to Assertions and must be rechecked by the service when needed.

## Conditional and Wildcard Fields

- Use ordinary Laravel rules for simple field-level conditions.
- Use `withValidator()` or `after()` when conditions depend on several sibling fields, parent values, wildcard indexes, or need errors attached to a different nested path.
- Build wildcard error paths explicitly and preserve the original input index. Do not rely on implicit behavior that could attach a failure to the wrong nested item.
- Keep validation messages translated and field-specific; do not use business exception messages for request validation.

## Rule Capabilities

- Implement `ValidationRule` for checks based on the current attribute value.
- Add `DataAwareRule` only when the dependency cannot remain visible through the rule constructor, its attachment point, or a Form Request validation hook. Store supplied data only for the current validation run.
- Add `ValidatorAwareRule` only when precise nested paths, multiple related failures, or validator state materially improves the error contract.
- A Rule may implement all three interfaces when the validation is genuinely composite, but its name and documentation must still describe one coherent input contract.
- Let ordinary Laravel rules reject invalid base types first. Return safely from the custom Rule when its input is not structurally usable.

## Database and Error Contract

- Collect unique identifiers and query them in bulk. Never issue one query per nested item or hide row-by-row `exists` checks inside a loop.
- Inspect the target model before querying and constrain `organization_id`, soft deletes, active state, status, parent ownership, and any authenticity boundary required by the input contract.
- Shared Rules must be module-agnostic and configurable. Business-specific Rules stay in their module.
- Collection Rules that extract a field must make that field explicit in their
  constructor, with `id` as the only permitted default when the collection is
  conventionally a list of identifiers. Preserve flat-list and object-list
  index semantics without inventing a parent-level error.
- Use translated messages. Prefer the `$fail` callback for the current attribute; use the injected Validator only when exact or multiple nested paths materially improve the error contract.
- Validation Rules report HTTP/input errors. They do not mutate data, start transactions, authorize, or throw business exceptions.

## Readability Threshold

- A Form Request should read primarily as an input schema. Extract a Rule when a long closure, repeated query configuration, or multi-stage nested validation forces the reader to understand implementation details before seeing the payload shape.
- Do not extract tiny one-off rules that are clearer inline.

## References

- `.ai/reference-examples/ReplaceOrderLinesRequest.php.example` shows a Form Request that stays readable as an input schema.
- `.ai/reference-examples/ValidOrderLines.php.example` shows one cohesive Rule implementing `ValidationRule`, `DataAwareRule`, and `ValidatorAwareRule`, with one tenant-scoped bulk lookup and precise nested errors.
- `app-modules/shared/src/Rules/BulkExists.php` and
  `app-modules/shared/src/Rules/BulkUnique.php` are the production references
  for configurable bulk Rules: they perform constrained lookups once and use
  `ValidatorAwareRule` to preserve precise indexed errors.
- `app-modules/*/src/Http/Requests/**` contains create/update Requests that merge rules from route-model context and normalize explicitly named fields.
