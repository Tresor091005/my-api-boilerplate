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

## 2. Rules by File Type

### 2.1 `routes/*-routes.php`
- The file must always be named `[module]-routes.php`.
- The URL prefix must always be `v1/[module]`.
- The route name prefix must always be `lahatre.[module].`.
- All API routes must use `api`.
- Authenticated routes must use `auth.api`.
- URIs must be RESTful, plural, `kebab-case`, and action-free.
- Prefer `Route::apiResources([...])` for simple CRUD groups.
- Nested resources are allowed.
- Every nested resource `parent.children` must explicitly enforce scoped binding.
- A nested route must never allow `parent A + child B` when no real relationship exists.
- Every route must be named.

### 2.2 `src/Http/Controllers/*Controller.php`
- Single responsibility: HTTP orchestration.
- No business logic.
- No manual business validation outside DTOs.
- The Controller authorizes access before calling the service.
- For nested routes:
  - reads: authorize the parent with `retrieve`
  - mutations: authorize the parent with `update`
  - then authorize the child model or target class for the action itself
- Standard flow:
  1. `Gate::authorize(...)`
  2. `DTO::fromRequest(...)` or `DTO::forUpdate(...)`
  3. service call
  4. `JsonResponse`, `JsonResource`, or collection response
- HTTP status codes:
  - `201` for create
  - `204` for delete
  - `200` otherwise

### 2.3 `src/Services/*Service.php`
- The service orchestrates business logic.
- The service must not know the HTTP request.
- The service works with DTOs, models, collections, and primitive values.
- `StandaloneService` implementations own transactions.
- `TransactionalService` implementations must never start their own transaction.
- No HTTP authorization logic inside services.
- If a service receives both a parent and a child, it may validate the parent/child invariant.
- Model assignment must be explicit, field by field.
- Hydrating a model with `$dto->toArray()` is forbidden.
- Eager loading is required when a resource depends on relations.
- Prefer bulk operations when they simplify the code without harming readability.
- Cursor pagination must always use deterministic ordering.
- Sort inputs must come from a DTO whitelist, not arbitrary request values.
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

### 2.6 `src/DTO/*.php`
- Single responsibility: transport, type, validate, and normalize input data.
- Must extend `Lahatre\Shared\DTO\LahatreDTO`.
- `casts()` is required for anything that is not a simple string.
- `defaults()` defines implicit values.
- `beforeValidation()` cleans raw input.
- `rules()` must be strict.
- `after()` handles complex validation.
- Use `str()` normalization macros when needed.
- DTO categories:
  - `[Entity]DTO`
  - `[Entity]FilterDTO`
  - `[Entity]DataDTO`
  - `[Entity][Action]DTO`

### 2.7 `src/Rules/*.php`
- Use for reusable or SQL-heavy field validation.
- Must implement `ValidationRule`.
- Failure messages must be translated.

### 2.8 `src/Policies/*.php`
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

### 2.9 `src/Http/Resources/*.php`
- Single responsibility: transform output.
- No business logic.
- `@mixin` is required.
- Use `whenLoaded()` by default for relations.
- Eager loading belongs to the Controller or Service.
- Collections must extend `App\Http\Resources\BaseCollection`.
- Avoid infinite serialization loops.

### 2.10 `src/Models/*.php`
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

### 2.11 `src/Providers/*.php`
- One provider per module by default.
- Use it for bindings, config merging, morph maps, and package registration.
- No business logic.

### 2.12 `src/Support/*.php`
- Business-agnostic helpers only.
- No implicit HTTP knowledge.
- No permissions or business orchestration.

### 2.13 `src/Integrations/*.php`
- Encapsulate external dependencies.
- No application business logic.
- Translate business needs into technical calls.

### 2.14 `database/migrations/*.php`
- Define the SQL schema.
- May include mandatory production reference data.
- Use `jsonb` for structured data.
- Use `text` for free text.
- Prefer `uuidMorphs()` and `foreignUuid()`.
- Foreign keys must be indexed.
- Logical unique combinations must be enforced.
- Tables must be prefixed by module.
- Production corrections should preferably be done through new migrations.

### 2.15 `database/factories/*.php`
- Dedicated to tests.
- Must generate coherent states.
- Must be correctly connected to models.

### 2.16 `database/seeders/*.php`
- Reserved for development and demo data.
- No production-critical data.
- Prefer idempotence through `firstOrCreate()`.

### 2.17 `resources/lang/en/*.php`
- Simple PHP files returning arrays.
- Keys must use `snake_case`.
- Dynamic placeholders must use `:placeholder`.
- Messages must be stable and explicit.
- English is the reference language.

### 2.18 `tests/Feature/**/*.php`
- Pest only.
- A test must document a clear business intent.
- Testing priorities:
  1. DTO validation
  2. business assertions
  3. service logic and persistence
  4. output contract
- Module-local tests stay inside `app-modules/<module>/tests`.
- Cross-module or real auth tests belong in `tests/Feature/Integration`.
- If the test answers “who is allowed?”, it is usually an integration HTTP test.
- If the test answers “what does the business do?”, it is usually a module-local test.

### 2.19 `tests/Feature/Architecture/**/*.php`
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
