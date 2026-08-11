---
name: project-memory
description: Living project context for agents and reviewers. Stores current architectural decisions, recurring review traps, and intentional constraints that are too specific or too fluid for static codebase rules.
---

# Project Memory

This document stores living project context for agents working in this repository.

Use it together with `CODEBASE_RULES.md`:
- `CODEBASE_RULES.md` defines stable rules.
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
- Put durable normative rules in `CODEBASE_RULES.md`.
- Put current context, tradeoffs, exceptions, and review watchpoints in `PROJECT_MEMORY.md`.
- If a point starts as memory and becomes stable, promote it into `CODEBASE_RULES.md`.

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

### 3.6 Inventory Package Boundary
- `inventory` is tenant-agnostic by design.
- The module is closer to a reusable package contract than to a tenant-scoped application module.
- `inventory` tables do not carry `organization_id`, and the module does not enforce host-application tenancy by itself.
- The current `inventory` routes are package-generic routes.
- In this repository, those routes must not be treated as safe final application routes for tenant-scoped exposure in their current form.
- UUID identifiers reduce trivial enumeration, but they are not an authorization boundary.
- If the host application needs tenant-safe inventory endpoints, it must add an explicit business access boundary through host-level routes, Controllers, parent resources, or authorization rules.

### 3.7 Form Request And Data Separation
- `LahatreDTO` and validated DTOs are retired architecture. New code must not combine Laravel validation with service transport objects.
- Form Requests own HTTP input validation and field-specific normalization.
- Controllers retain Gate and Policy authorization. Because injected Form Requests validate before the Controller executes, an authenticated but unauthorized request may receive a validation response before the Controller Gate runs; this ordering is an accepted tradeoff and route rate limiting remains required.
- Data classes are immutable typed transport objects created through `fromArray()` and contain no HTTP, Eloquent, Validator, authorization, or tenant-context knowledge.
- The project favors one Request and one Data class per coherent shape, not one class per Controller action. Store/update classes are split only when conditional rules or missing-value semantics make the shared shape difficult to understand.
- `MissingValue` represents an absent key and must remain distinct from explicit `null`, `false`, zero, an empty string, or an empty array.
- `missingFields` passed to `fromArray()` uses source `snake_case` keys. Services consume `camelCase` Data properties.
- Model updates should use an explicit field map filtered through the shared missing-value helper so absent attributes are not assigned.

## 4. Known Review Traps

- A nested route can look correct while still missing scoped binding.
- A controller can authorize the child but forget the parent on nested routes.
- A service can quietly reintroduce tenancy or HTTP permission checks that should live in controllers and policies.
- A query can look technically correct while silently missing its tenancy boundary or its soft-delete boundary.
- A package-oriented module route can look acceptable while still being unsafe for direct exposure in a tenant-scoped host application.
- A module exception can extend `Exception` instead of `AssertionException`, which bypasses the intended render contract.
- User-facing text can drift into code instead of translations.
- Test code often hides architecture mistakes because it bypasses the HTTP layer.
- `nullable` does not mean that a key was present; use `array_key_exists()` while building Data objects.
- Filtering Data values must remove only `MissingValue`; it must preserve `null`, `false`, zero, empty strings, and empty arrays.
- A shared store/update Form Request should be split once conditional branches obscure its input contract.

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
- permanent coding standards that should live in `CODEBASE_RULES.md`.
