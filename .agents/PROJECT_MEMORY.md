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

## 4. Known Review Traps

- A nested route can look correct while still missing scoped binding.
- A controller can authorize the child but forget the parent on nested routes.
- A service can quietly reintroduce tenancy or HTTP permission checks that should live in controllers and policies.
- A module exception can extend `Exception` instead of `AssertionException`, which bypasses the intended render contract.
- User-facing text can drift into code instead of translations.
- Test code often hides architecture mistakes because it bypasses the HTTP layer.

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
