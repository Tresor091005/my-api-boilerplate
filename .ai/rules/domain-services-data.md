---
paths:
  - app-modules/*/src/Services/**
  - app-modules/*/src/Data/**
  - app-modules/*/src/Assertions/**
  - app-modules/*/src/Exceptions/**
  - app-modules/*/src/ViewData/**
---

# Domain Services and Data

## Services

- Treat each public method as one straightforward application use case that may be called from HTTP, Console, a Job, or a Scheduler. Do not depend on Requests, Controllers, response codes, middleware execution, or HTTP authorization.
- The caller authorizes and establishes tenant context. The service resolves or validates the required tenant before its first tenant-owned query, so non-HTTP callers cannot accidentally bypass context setup.
- Accept typed Data, models, Collections, and primitives. Map Data to model fields explicitly; never hydrate a model from an entire Data object or generic `toArray()` result.

### Fail-Fast Orchestration

- Reject invalid conditions first, then prepare evidence, perform mutations, and build the result.
- Delegate reusable persistent invariants to a model- or aggregate-centered Assertion.
- Use a direct condition plus a concrete business exception for a small invariant local to one use case.
- Use protected/private methods for cohesive named steps that belong only to the current service. Extract a transactional support service when the mutation step is reusable or needs its own dependencies.
- Avoid deep conditional nesting. Keep the successful path readable and return early from invalid or irrelevant branches.

### Transactions, Concurrency, and Side Effects

- A standalone public mutation owns the complete transaction. A transactional support service assumes its caller's transaction and never opens another one. Read-only methods do not start transactions by default.
- Check race-sensitive preconditions inside the transaction. Protect them with a unique constraint, atomic statement, `lockForUpdate()`, or another appropriate concurrency mechanism when a second writer could invalidate the check.
- Let failures roll back naturally. Catch only to translate an infrastructure failure into the intended domain contract, add meaningful context, or perform required cleanup.
- Trigger Jobs, events, notifications, external calls, and cache invalidation after commit when they depend on successful persistence.
- Make retryable operations idempotent when duplicate execution is unsafe. Use idempotency keys, unique constraints, atomic writes, or locks according to the operation.

### Queries and Persistence

- Prefer bulk reads and writes; preload and key evidence once, then pass it to Assertions or transactional support services. Avoid queries and lazy loading inside loops.
- Follow `.ai/rules/persistence-tenancy.md` for tenant and soft-delete boundaries, PostgreSQL-specific queries, index usage, batching, concurrency, and deterministic cursor pagination.

### Output Contract

- Keep the current project convention until an explicit output-boundary refactor is approved.
- Returning API Resources is intentional output coupling while services remain
  callable from HTTP, Console, Jobs, and Schedulers. A future move to models or
  application result objects must update services, Controllers, tests, and
  consumers deliberately; do not perform it as an incidental cleanup.
- Return a Resource for a model-backed result, a `Lahatre\Shared\Http\Resources\BaseCollection` subclass for a cursor-paginated list, ViewData for a computed or aggregated projection, and `void` when the operation has no representation.
- Prefer a Resource when the output is centered on an Eloquent model, collection, or query row. Resources may expose optional relations and aggregates with `whenLoaded()`, `whenCounted()`, and conditional fields.
- Use ViewData only when the result has no natural model, collection, or query-row Resource, such as a multi-level aggregate or calculated projection assembled from several sources.
- The service performs queries, grouping, calculations, and data preparation. ViewData defines only the named typed output shape and its `toArray()`/JSON serialization.
- Load every relation or aggregate required by a Resource or ViewData before constructing the output.

### Documentation

- Public methods with non-obvious behavior document their use-case intent, transaction ownership, required tenant context, durable side effects, and every concrete business exception.
- Use native return types and PHPDoc for complex Collection, array, or callback shapes.

Reference: `.ai/reference-examples/OrderService.php.example` shows transaction ownership, service-provided Assertion evidence, explicit Data mapping, a Resource result, a `void` deletion, and a ViewData projection.

## Data Classes

- Use immutable typed Data classes, normally `final readonly` with a private constructor and a `fromArray()` factory.
- Keep Data independent from Form Requests, Eloquent models, Laravel validation, authorization, and ambient tenant context.
- `LahatreDTO` and validated DTOs are retired architecture; do not reintroduce
  validation into service transport objects.
- Map source `snake_case` keys to `camelCase` properties explicitly. Convert already validated values into enums, dates, Collections, or nested Data objects in `fromArray()`.
- Use one Data class per coherent service shape. Split action-specific classes only when the shapes materially differ.
- `MissingValue` is a small typed marker for partial service updates: it distinguishes an omitted field from an explicitly supplied `null` or another value. It is not a validation system.
- Put `MissingValue` first in unions, use `array_key_exists()` when building the Data object, and keep `missingFields` in source `snake_case`.
- Services map Data properties explicitly and use `withoutMissing()` for partial model updates. It removes only `MissingValue`, preserving `null`, `false`, zero, empty strings, and empty arrays.

Reference: `.ai/reference-examples/ReplaceOrderLinesData.php.example` shows one coherent Data object mapping a validated nested `snake_case` payload into typed `camelCase` service data.

## Assertions and Exceptions

- Name an Assertion after the business model or aggregate whose persistent invariants it protects, such as `CategoryAssertion` or `UnitAssertion`.
- Public methods start with `assert`, return `void`, and describe an operation the service is about to perform. They are silent on success and throw concrete business exceptions on failure.
- Pass the models, Data objects, and typed Collections the service already loaded or will need for persistence. A focused `exists()`, `count()`, or constrained lookup is appropriate only when the assertion needs a fact the service does not otherwise need.
- Use Assertions to verify allowed state transitions, model authenticity, parent/child and tenant consistency, and whether supplied domain objects may participate in the same operation.
- Assertions do not authorize HTTP access, validate request shape, mutate persistence, or own transactions.
- Document every public assertion with the business intention, valid outcome, complex parameter shapes, and every concrete `@throws` possibility. Split complex checks into documented protected helpers.
- If the invariant, its required evidence, or its ownership is ambiguous, ask the developer before creating queries or widening the assertion contract.
- Business failures extend `Lahatre\Shared\Exceptions\AssertionException`. Keep them minimal: expose a clearly named static factory for each business condition, pass the corresponding module translation key to the parent constructor, and include only useful, stable context.
- Factory names must describe the failure (`productsUnavailable()`, `hasLines()`), not hide it behind generic names such as `invalid()` or `failed()`.
- Keep construction private and generic so exceptions can only be created through their named factories: `private function __construct(string $message, array $context = []) { parent::__construct($message, $context); }`.
- Never throw a bare `Exception` from `app-modules/*/src` for a business failure.
- The bootstrap rendering of `AssertionException` is part of the API contract;
  preserve its translated message and stable structured context.

References:

- `.ai/reference-examples/OrderAssertion.php.example` shows service-provided evidence, a focused `exists()` query, `void` methods, intent documentation, and concrete `@throws` declarations.
- `.ai/reference-examples/OrderException.php.example` shows translated failures with stable structured context.

## ViewData

- ViewData is an immutable, named output projection for computed or aggregated reads that are not naturally represented by one Eloquent model.
- Data enters a service; ViewData leaves a service. API Resources remain the default for Eloquent models, collections, query rows, loaded relations, and loaded aggregates; ViewData represents already-computed output with no natural Resource boundary.
- Services and query services perform queries, calculations, formatting that needs a dependency, and tenant resolution before constructing ViewData.
- ViewData must not query, resolve container services, authorize, validate input, or read ambient tenant context.
- Prefer `final readonly`, typed constructor properties, and typed nested ViewData Collections. Implement `Arrayable` and `JsonSerializable` for direct HTTP output, with `jsonSerialize()` delegating to `toArray()`.
- Use `snake_case` serialization keys and document exact array shapes. Do not create ViewData merely to wrap an unstructured array.

References:

- `.ai/reference-examples/OrderSummaryViewData.php.example` shows a root computed projection with an exact nested output shape.
- `.ai/reference-examples/OrderLineSummaryViewData.php.example` shows the small typed ViewData used for each nested row.
