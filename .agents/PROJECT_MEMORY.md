---
name: project-memory
description: Living project context for agents and reviewers. Stores current architectural decisions, recurring review traps, and intentional constraints that are too specific or too fluid for static codebase rules.
---

# Project Memory

This document stores living project context for agents working in this repository.

Use it together with `.ai/rules/` and `CODEBASE_RULES.md`:
- `.ai/rules/*.md` defines detailed, path-scoped normative rules.
- `CODEBASE_RULES.md` defines only universal invariants and conflict resolution.
- `PROJECT_MEMORY.md` captures current decisions, review traps, and local context that may evolve over time.

## 1. Purpose

- Keep non-obvious project decisions visible to generators and reviewers.
- Prevent architectural drift caused by forgotten past decisions.
- Record the intent behind local conventions that are easy to miss in the code alone.
- Store context that is too operational or too fluid to belong in permanent technical documentation.

## 2. Working Assumptions For Agents

- The developer remains the decision maker.
- Generated code is a proposal until it is understood, reviewed, and validated.
- Never put secrets, credentials, personal data, or sensitive business data into prompts.
- Be explicit rather than clever when modifying this codebase.
- If a useful recurring prompt or workflow emerges, fold the insight into rules or memory instead of relying on personal habit.

## 3. Current Architectural Decisions

### 3.1 Rules Vs Memory
- Put detailed durable normative rules in the appropriate `.ai/rules/*.md` file.
- Put universal invariants and conflict resolution in `CODEBASE_RULES.md`.
- Put current context, tradeoffs, exceptions, and review watchpoints in `PROJECT_MEMORY.md`.
- If a point starts as memory and becomes stable, promote it to the appropriate rule file rather than expanding a skill.

### 3.2 Nested Resources
- Nested HTTP resources must rely on scoped routing for real parent/child binding.
- Controllers must authorize both the parent and the child when the route is nested.
- Services must not duplicate HTTP tenancy checks when the controller and route already guarantee them.
- If a service is later reused by jobs, commands, or another entry point, that caller must enforce its own authorization contract explicitly.

### 3.3 Business Exceptions
- Business-facing failures should flow through `AssertionException`.
- The bootstrap rendering for `AssertionException` is part of the intended API contract.
- Review any new business exception for translation, context stability, and correct inheritance.

### 3.4 Auth Service Contract
- `AuthService` currently accepts the IAM `User` model explicitly.
- In `AuthController`, runtime assertions are intentionally kept before calling `AuthService`.
- If multi-guard or multi-authenticatable support is introduced later, widen the service contract deliberately instead of silently loosening controller assumptions.

### 3.5 Query Boundaries
- `deleted_at` and `organization_id` are cross-cutting boundaries in this codebase, but they are not enforced in the same way.
- `SoftDeletes` is trusted on normal Eloquent model queries, so reviewers should focus extra attention on `DB::table(...)`, joins, grouped reads, and raw queries touching soft-deletable tables.
- `organization_id` does not have a universal global scope. Reviewers must be able to point to the exact tenancy boundary of a query:
  - an explicit `organization_id` constraint,
  - an explicit system-plus-tenant rule such as `organization_id IS NULL OR organization_id = current team`,
  - or an already authorized parent model that clearly constrains the child query.
- If that proof is not locally visible, raise a warning and expect an immediate fix unless the surrounding flow makes the boundary obvious.

### 3.6 Inventory Tenancy Boundary
- `inventory` is tenant-scoped in the current architecture.
- Inventory items, locations, stocks, transactions, and movements carry `organization_id`.
- Inventory services resolve the active organization through `ResolvesInventoryOrganization`, constrain queries by that organization, and reject resolved models from another organization.
- Inventory HTTP routes use `auth.api`, but authentication and organization scoping do not replace permission authorization in Controllers and Policies.
- UUID identifiers reduce trivial enumeration, but they are not an authorization boundary.
- Any new inventory query, aggregate, mutation, index, or uniqueness rule must preserve the organization boundary explicitly.

### 3.7 Form Request And Data Separation
- `LahatreDTO` and validated DTOs are retired architecture. New code must not combine Laravel validation with service transport objects.
- Form Requests own HTTP input validation and field-specific normalization.
- Controllers retain Gate and Policy authorization. Because injected Form Requests validate before the Controller executes, an authenticated but unauthorized request may receive a validation response before the Controller Gate runs; this ordering is an accepted tradeoff and route rate limiting remains required.
- Data classes are immutable typed transport objects created through `fromArray()` and contain no HTTP, Eloquent, Validator, authorization, or tenant-context knowledge.
- The project favors one Request and one Data class per coherent shape, not one class per Controller action. Store/update classes are split only when conditional rules or missing-value semantics make the shared shape difficult to understand.
- `MissingValue` represents an absent key and must remain distinct from explicit `null`, `false`, zero, an empty string, or an empty array.
- `missingFields` passed to `fromArray()` uses source `snake_case` keys. Services consume `camelCase` Data properties.
- Model updates should use an explicit field map filtered through the shared missing-value helper so absent attributes are not assigned.

### 3.8 Service Output Boundary
- Services remain callable from HTTP, Console, Jobs, and Schedulers at the input and orchestration boundary.
- For now, this project intentionally allows services to return API Resources and Resource Collections for model-backed results and cursor-paginated lists.
- Computed and aggregated read projections return ViewData; operations without a representation may return `void`.
- This accepted output coupling may later be replaced with models, model Collections, or application result objects so each caller controls presentation.
- Do not perform that refactor opportunistically. Change the output boundary deliberately across the service, Controller, tests, and consumers.

## 4. Known Review Traps

- A nested route can look correct while still missing scoped binding.
- A controller can authorize the child but forget the parent on nested routes.
- A service can quietly reintroduce tenancy or HTTP permission checks that should live in controllers and policies.
- A query can look technically correct while silently missing its tenancy boundary or its soft-delete boundary.
- An authenticated inventory route can look tenant-safe while still missing explicit permission authorization in its Controller or Policy.
- A module exception can extend `Exception` instead of `AssertionException`, which bypasses the intended render contract.
- User-facing text can drift into code instead of translations.
- Test code often hides architecture mistakes because it bypasses the HTTP layer.
- `nullable` does not mean that a key was present; use `array_key_exists()` while building Data objects.
- Filtering Data values must remove only `MissingValue`; it must preserve `null`, `false`, zero, empty strings, and empty arrays.
- A shared store/update Form Request should be split once conditional branches obscure its input contract.
- A service intended for Jobs or Schedulers can silently assume tenant middleware ran; require the caller to establish context and make the service validate it before tenant-owned queries.
- A precondition can pass and become false before persistence; protect race-sensitive invariants inside the transaction with a database constraint, atomic statement, or lock.
- A Job, event, notification, cache invalidation, or external call can run before a transaction commits; defer commit-dependent effects until after commit.

## 5. Testing And Tooling Reality

- `composer quality` is the default quality gate: `ide-helper`, `rector`, `pint`, `phpstan`.
- Pest tests are part of the normal workflow, but PHPStan does not fully understand Pest closure `$this` binding in this repository.
- PHPStan is intentionally configured to ignore specific Pest false positives in `tests/**` and `app-modules/*/tests/**`.
- Those ignores are there to suppress framework-analysis noise, not to lower standards for production code.
- Cross-module authorization behavior belongs in application integration tests, not module-local service tests.

## 6. Review Heuristics

- First check file responsibility before checking style.
- Prefer catching architectural leakage over suggesting cosmetic refactors.
- When a change touches HTTP code, check route binding, parent authorization, child authorization, DTO usage, and response contract together.
- When a change touches services, check transaction ownership, model hydration style, and relation loading expectations.
- When a change touches tests, verify that the test scope matches the architectural question it is asserting.

## 7. When To Update This File

Update `PROJECT_MEMORY.md` when:
- a recurring review comment appears more than once,
- a local convention is important but still too fluid for hard rules,
- an intentional compromise needs to be remembered,
- a refactor changes where responsibility is expected to live,
- tooling limitations affect how quality gates are interpreted.

Do not use this file for:
- endpoint reference documentation,
- onboarding tutorials,
- exhaustive architecture explanation,
- module API reference,
- permanent detailed coding standards that should live in the appropriate
  `.ai/rules/*.md` file, or universal invariants that belong in
  `CODEBASE_RULES.md`.
