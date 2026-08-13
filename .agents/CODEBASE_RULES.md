---
name: codebase-rules
description: Global invariants for this modular Laravel codebase. Detailed, path-scoped rules live in .ai/rules.
---

# Global Codebase Invariants

This file contains only rules that apply everywhere. Detailed conventions are
owned by the matching files in `.ai/rules/`; agents must use
`.ai/rules/index.md` to discover them.

## Authority and Navigation

- `.ai/rules/index.md` is the entry point for generation and review.
- Every file in scope must be matched against every applicable row in the
  index, and every matching rule file must be read.
- `.ai/rules/*.md` contains the normative detailed rules for its path or
  domain. Existing code and examples are evidence, not permission to repeat a
  known violation.
- `.agents/PROJECT_MEMORY.md` contains contextual decisions, trade-offs,
  exceptions, and review traps. It does not override a rule.
- If two rules conflict, stop and reconcile the rules before generating or
  approving code. Do not resolve the conflict by personal preference.

## Universal Code Standards

- Keep each file within its declared responsibility. Prefer explicit,
  readable code over clever compression.
- Use English for code, comments, PHPDoc, test names, rule text, and technical
  documentation. User-facing text follows the localization rules.
- Use complete native return types and PHPDoc when the native signature cannot
  express the shape.
- Use `camelCase` for PHP identifiers, `snake_case` for database columns,
  payload keys, and translation keys, and lowercase dot-separated route names.
- Do not hardcode user-facing text. Resolve it through the owning translation
  namespace and keep machine context separate from translated messages.
- Business exceptions inside `app-modules/*/src` extend
  `Lahatre\Shared\Exceptions\AssertionException`; do not throw generic
  `Exception` instances there.

## Module and Dependency Boundaries

- `app-modules/<module>` owns its source, routes, configuration, database,
  resources, translations, and module-local tests.
- Module code uses the `Lahatre\<Module>\` namespace and its Composer PSR-4
  mapping; do not place module code under the root `App\` namespace.
- Module dependencies form an explicit acyclic graph. Cross-module consumers
  use public Contracts or documented services and do not modify another
  module's internals or database objects.
- Database queries in every application layer must make their tenant and
  soft-delete boundaries locally provable. Raw SQL, joins, aggregates,
  subqueries, and `DB::table()` calls must state all relevant boundaries.
- HTTP authorization belongs to Controllers and Policies; reusable business
  invariants belong to services and Assertions. Do not use validation as an
  authorization layer.

## Change and Verification Discipline

- A behavior change requires an affected Pest test according to
  `.ai/rules/testing.md`.
- Run the smallest relevant test or static check first, then the appropriate
  complete quality gate when the scope warrants it.
- Do not delete tests or weaken a rule to make a check pass without explicit
  approval.
- When a rule is missing or ambiguous, report the gap and propose an update to
  the appropriate `.ai/rules/*.md` file, this global document, or
  `PROJECT_MEMORY.md` before silently inventing a convention.

## Skill Contract

- `code-generator` and `code-reviewer` are the only project-specific skills.
- Both must read `.ai/rules/index.md`, all matching rules, this file, and
  `PROJECT_MEMORY.md` before acting.
- Skills provide procedural workflows only; they must not duplicate detailed
  project doctrine.
