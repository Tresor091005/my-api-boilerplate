---
name: code-reviewer
description: Code review workflow for this modular Laravel codebase. It must read the central codebase rules and project memory before auditing files.
---

# Skill: Code Reviewer

This skill reviews code against the project source of truth and current project memory. The `.ai/rules` tree is the working navigation layer for selecting applicable rules.

## Source of Truth

Before any review, always read:

`.ai/rules/index.md`
`.agents/CODEBASE_RULES.md`
`.agents/PROJECT_MEMORY.md`

## Review Process

When asked to review a file or directory:

1. Identify every file type and path in scope.
2. Find every matching row in `.ai/rules/index.md` and read every applicable `.ai/rules/*.md` file.
3. Load the global invariants in `CODEBASE_RULES.md`.
4. Read `PROJECT_MEMORY.md` for current decisions, review traps, and intentional constraints.
5. Check:
   - file responsibility
   - architectural compliance
   - authorization and nested binding rules for HTTP code
   - query boundaries for tenancy and soft deletes
   - current database schema for persistence-related findings
   - exception and translation contract for business code
   - minimum style expectations
6. List each violation with:
   - file and line
   - violated rule
   - concrete impact
   - expected correction

## Finding Priorities

1. Responsibility violation.
2. Functional regression.
3. Security or authorization gap.
4. Durable convention violation.
5. Style debt.

## Watch Points

- `whenLoaded()` in resources.
- Eager loading on the service side.
- No `throw new \Exception(...)` inside modules.
- Nested resources are properly scoped.
- Parent and child authorization both exist in nested Controllers.
- Every tenant-owned or soft-deletable query has a locally provable boundary.
- Business exceptions are translated through `AssertionException`.
- If a reviewed file contains another language without a strong reason, translate it to English as part of the change.
- Rules are normative; `CODEBASE_RULES.md` supplies universal guardrails and
  memory is contextual. Neither examples nor existing code override a rule.
- Do not report a violation merely because a file differs from an example; cite
  the applicable rule or label the concern as an unverified gap.
- Run the smallest relevant test or static check available and report what was
  and was not verified. Do not claim total compliance from a narrow command.

## Current Database Schema

For reviews involving tables, columns, indexes, foreign keys, or constraints,
inspect the database currently used by the application before drawing schema
conclusions from migration source:

```bash
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan db:show --counts --views
docker compose exec -T app php artisan db:table <table>
```

Use `migrate:status` to establish which migrations are applied, `db:show` to
inventory the installed database, and `db:table` to verify the actual columns,
indexes, and foreign keys of relevant tables. Use migration files afterward to
explain schema history or identify the migration that should correct the
current database. Never treat a column present only in an old migration, a
down method, or model PHPDoc as part of the current schema.
