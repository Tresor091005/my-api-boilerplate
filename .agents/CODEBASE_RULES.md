---
name: codebase-rules
description: Single source of truth for this modular Laravel codebase. Generation, review, localization, and testing workflows must read this document before acting.
---

# Codebase Rules

This document is the single source of truth for the codebase rules.

## 1. Global Principles

### 1.1 Language Rule
- Everything must be written in English.
- Code, comments, PHPDoc, exception messages, translation values, test names, and rule descriptions must be in English.
- If a reviewed or edited file contains another language without a strong technical reason, translate it to English immediately.
- Do not scan the whole project just for language. Apply this rule to the files currently being analyzed, reviewed, generated, or edited.

### 1.2 Golden Rule
- Every file must strictly respect its responsibility.
- If a file goes beyond its role, it is an architecture violation unless an explicit rule says otherwise.

### 1.3 Readability
- Readability over brevity.
- Prefer explicit code over compact code.
- Add return types everywhere.
- Add PHPDoc when the PHP signature is not expressive enough.

### 1.4 Naming
- Use `camelCase` in PHP code.
- Use `snake_case` for database columns, frontend payloads, JSON keys, and translation keys.
- Route names must be lowercase and dot-separated.

### 1.5 Collections and Helpers
- Prefer Laravel `Collection` over raw `array` for business flows.
- Use `str()`, `data_get()`, and `optional()` when they clearly improve readability.

### 1.6 User-Facing Text
- No hardcoded user-facing text in business code.
- Messages must go through `__('module::file.key')`.
- Business exceptions must use translations.

### 1.7 Business Exception Contract
- Every business exception must extend `Lahatre\Shared\Exceptions\AssertionException`.
- The constructor must pass a translated message and, when useful, a structured `context`.
- `throw new \Exception(...)` is forbidden inside `app-modules/*/src`.

### 1.8 Tenancy and Authorization
- HTTP authorization belongs to Controllers and Policies.
- Nested routes must use scoped parent/child binding.
- A service must not be the first line of defense for HTTP permissions.
- A service may still protect a domain invariant when it receives both parent and child models.
- Every read path must make its tenancy boundary and soft-delete boundary locally provable.

## 2. Rules by File Type

### 2.1 `routes/*-routes.php`
- The file must always be named `[module]-routes.php`.
- The URL prefix must always be `v1/[module]`.
- The route name prefix must always be `lahatre.[module].`.
- All API routes must use `api`.
- Authenticated routes must use `auth.api`.
- Package-oriented routes are allowed only when the module is intentionally host-agnostic.
- A tenant-scoped host application must not expose package-generic routes directly when they do not enforce the host application's access boundary.
- URIs must be RESTful, plural, `kebab-case`, and action-free.
- Prefer `Route::apiResources([...])` for simple CRUD groups.
- Nested resources are allowed.
- Every nested resource `parent.children` must explicitly enforce scoped binding.
- A nested route must never allow `parent A + child B` when no real relationship exists.
- Every route must be named.

### 2.2 `src/Http/Controllers/*Controller.php`
- Single responsibility: HTTP orchestration.
- No business logic.
- No manual input validation inside Controllers.
- The Controller authorizes access before calling the service.
- If a module is package-oriented and tenant-agnostic, host-application Controllers or routes must add the missing business access boundary before exposing it.
- For nested routes:
  - reads: authorize the parent with `retrieve`
  - mutations: authorize the parent with `update`
  - then authorize the child model or target class for the action itself
- Standard flow:
  1. `Gate::authorize(...)`
  2. build a Data object with `Data::fromArray($request->validated())`
  3. service call
  4. `JsonResponse`, `JsonResource`, or collection response
- HTTP status codes:
  - `201` for create
  - `204` for delete
  - `200` otherwise

### 2.3 `src/Services/*Service.php`
- The service orchestrates business logic.
- The service must not know the HTTP request.
- The service works with Data objects, models, collections, and primitive values.
- `StandaloneService` implementations own transactions.
- `TransactionalService` implementations must never start their own transaction.
- No HTTP authorization logic inside services.
- If a service receives both a parent and a child, it may validate the parent/child invariant.
- Model assignment must be explicit, field by field.
- Hydrating a model with `$dto->toArray()` is forbidden.
- Eager loading is required when a resource depends on relations.
- Prefer bulk operations when they simplify the code without harming readability.
- For tenant-owned data, each new query must make its tenancy boundary explicit or clearly inherit it from an already authorized and constrained parent model.
- For soft-deletable tables, Eloquent model queries may rely on `SoftDeletes` by default, but `DB::table(...)`, joins, aggregates, and raw queries must make the `deleted_at` boundary explicit when it matters.
- When a reviewer cannot locally prove the tenancy or soft-delete boundary of a new query, it is a warning that should be treated as an immediate fix unless the surrounding flow already constrains it in an obvious and documented way.
- Cursor pagination must always use deterministic ordering.
- Sort inputs must come from a Data whitelist, not arbitrary request values.
- If the effective cursor order is not already unique, append a unique tie-breaker, usually `id`, using the same direction as the last explicit sort.
- When the standard cursor filter quartet is used (`sort_by`, `sort_order`, `per_page`, `cursor`), prefer the shared `stableCursorPaginate()` helper.
- If a query needs custom ordering logic beyond that quartet, write the `orderBy(...)` chain explicitly in the service and finish with `cursorPaginate(...)`.

### 2.4 `src/Assertions/*Assertion.php`
- Single responsibility: validate a business rule.
- Public methods must start with `assert`.
- An assertion throws an `AssertionException` when the rule fails.
- Public methods should map as closely as possible to service intentions.
- Complex validations should be split into `protected` helper methods.
- PHPDoc is required for:
  - business intent
  - complex `@param`
  - `@throws`

### 2.5 `src/Exceptions/**/*.php`
- Every business exception must extend `AssertionException`.
- Exception placement should follow the related business entity when it makes sense.
- The constructor must pass a translated message.
- `context` must be useful and stable.
- No hardcoded user-facing text.

### 2.6 `src/Http/Requests/*Request.php`
- Single responsibility: validate and normalize HTTP input.
- HTTP authorization remains in Controllers and Policies; a Form Request must not contain policy or Gate logic.
- A Form Request may be shared by multiple Controller actions while their input shape remains coherent.
- Use action-specific Requests only when the shapes materially diverge.
- `prepareForValidation()` only normalizes fields that explicitly require it; never apply recursive normalization to all input strings.
- `rules()` must be strict and may use the route model to adapt rules such as `unique()->ignore(...)`.
- Presence rules must be intentional:
  - no implicit presence rule when absence is accepted
  - `nullable` when `null` is accepted
  - `present` when the key must exist but an empty value may be valid
  - `required` when the key must exist and contain a non-empty value
- `present|array` is sufficient to require an array key while allowing an empty array; `min:0` is redundant.
- `after()` handles complex HTTP input validation.
- Custom messages and attributes must use translations when they are user-facing.

### 2.7 `src/Data/*.php`
- Single responsibility: immutable, typed transport for services.
- Data classes must be independent from HTTP Requests, Eloquent Models, Laravel Validator, authorization, and ambient tenant context.
- Prefer `final readonly class` with a private constructor.
- `fromArray()` is the conventional construction entry point and must perform the `new self(...)` call.
- `fromArray()` maps `snake_case` payload or persistence keys to `camelCase` PHP properties explicitly.
- Data factories may convert already validated values into enums, dates, collections, and nested Data objects, but must not perform HTTP validation.
- One Data class represents one coherent service shape. Do not create action-specific Data classes when the shape is effectively identical.
- `MissingValue` is allowed only when absence has a different meaning from an explicit `null` value.
- When a Data property accepts `MissingValue`, list it first in the union (`MissingValue|string|null`, `MissingValue|array|null`, `MissingValue|bool`) so absence is visible before value types.
- A `missingFields` argument refers to source array keys and therefore uses `snake_case`.
- Use `array_key_exists()` whenever explicit `null` must remain distinguishable from an absent key.
- Services must map Data properties to models explicitly; passing a whole Data object as a model attribute array is forbidden.
- Data categories should use intent-revealing names such as `[Entity]Data`, `[Entity]FilterData`, or `[Action]Data`.

### 2.8 `src/Rules/*.php`
- Use for reusable or SQL-heavy field validation.
- Must implement `ValidationRule`.
- Failure messages must be translated.

### 2.9 `src/Policies/*.php`
- Policies must stay simple.
- No SQL queries.
- No heavy business logic.
- Standard methods:
  - `list`
  - `retrieve`
  - `create`
  - `update`
  - `delete`
- `restore` and `forceDelete` must return `false` by default.
- Tenancy checks should rely on `organization_id` when the model has it.

### 2.10 `src/Http/Resources/*.php`
- Single responsibility: transform output.
- No business logic.
- `@mixin` is required.
- Use `whenLoaded()` by default for relations.
- Eager loading belongs to the Controller or Service.
- Collections must extend `App\Http\Resources\BaseCollection`.
- Avoid infinite serialization loops.

### 2.11 `src/Models/*.php`
- An explicit `$table` is required.
- `$fillable` must be limited to user/system writable fields.
- `$casts` must be explicit for all meaningful business columns.
- `id` and `*_id` must be cast to `string`.
- Dates must use `immutable_datetime`.
- Relations must be typed and explicit.
- Accessors and mutators must use `Attribute`.
- Recommended order:
  1. traits
  2. `$table`
  3. primary key metadata if needed
  4. `$fillable`
  5. `$casts`
  6. accessors/mutators
  7. relations
  8. scopes

### 2.12 `src/Providers/*.php`
- One provider per module by default.
- Use it for bindings, config merging, morph maps, and package registration.
- No business logic.

### 2.13 `src/Support/*.php`
- Business-agnostic helpers only.
- No implicit HTTP knowledge.
- No permissions or business orchestration.

### 2.14 `src/Integrations/*.php`
- Encapsulate external dependencies.
- No application business logic.
- Translate business needs into technical calls.

### 2.15 `database/migrations/*.php`
- Define the SQL schema.
- May include mandatory production reference data.
- Use `jsonb` for structured data.
- Use `text` for free text.
- Prefer `uuidMorphs()` and `foreignUuid()`.
- Foreign keys must be indexed.
- Logical unique combinations must be enforced.
- Tables must be prefixed by module.
- Production corrections should preferably be done through new migrations.

### 2.16 `database/factories/*.php`
- Dedicated to tests.
- Must generate coherent states.
- Must be correctly connected to models.

### 2.17 `database/seeders/*.php`
- Reserved for development and demo data.
- No production-critical data.
- Prefer idempotence through `firstOrCreate()`.

### 2.18 `resources/lang/en/*.php`
- Simple PHP files returning arrays.
- Keys must use `snake_case`.
- Dynamic placeholders must use `:placeholder`.
- Messages must be stable and explicit.
- English is the reference language.

### 2.19 `tests/Feature/**/*.php`
- Pest only.
- A test must document a clear business intent.
- Testing priorities:
  1. Form Request validation and Data mapping
  2. business assertions
  3. service logic and persistence
  4. output contract
- Module-local tests stay inside `app-modules/<module>/tests`.
- Cross-module or real auth tests belong in `tests/Feature/Integration`.
- If the test answers “who is allowed?”, it is usually an integration HTTP test.
- If the test answers “what does the business do?”, it is usually a module-local test.

### 2.20 `tests/Feature/Architecture/**/*.php`
- Every important cross-cutting rule deserves an architecture or HTTP guard.
- If a convention has already drifted once, it should ideally be protected by a test.

## 3. Decision Rules

### 3.1 Put a Rule in a Controller When
- The rule concerns HTTP access, policies, parent/child route composition, or response status.

### 3.2 Put a Rule in a Service When
- The rule concerns business logic, transactions, persistence, or invariants between already resolved objects.

### 3.3 Keep a Guard on Both Sides When
- Controller: access control
- Service: reusable business invariant

## 4. How Skills Must Use This Document

### 4.1 `code-generator`
- Must load this document before generating.
- Must generate according to the section matching the target file type.
- When in doubt, this document overrides habit or shortcut.

### 4.2 `code-reviewer`
- Must load this document before reviewing.
- The review report must cite the violated rule by file type.
- Findings must prioritize:
  1. responsibility violation
  2. functional regression
  3. security or authorization gap
  4. durable convention violation

### 4.3 `localization-manager`
- Becomes a specialized workflow, not a source of truth.
- Localization rules come from this document.

### 4.4 `test-generator`
- Becomes a specialized workflow, not a source of truth.
- Testing rules come from this document.

### 4.5 `module-manager`
- Becomes a structure and command workflow, not a source of truth.
- File and namespace conventions come from this document.

## 5. Evolution Policy

- If a rule must change, change this document first.
- Skills must not duplicate core codebase doctrine.
- Any rule duplicated inside a skill must stay minimal and procedural.
