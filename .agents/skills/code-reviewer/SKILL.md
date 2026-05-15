---
name: code-reviewer
description: Code review workflow for this modular Laravel codebase. It must read the central codebase rules and project memory before auditing files.
---

# Skill: Code Reviewer

This skill reviews code against the project source of truth and current project memory.

## Source of Truth

Before any review, always read:

`/Users/imac/Documents/my-api-boilerplate/.agents/CODEBASE_RULES.md`
`/Users/imac/Documents/my-api-boilerplate/.agents/PROJECT_MEMORY.md`

## Review Process

When asked to review a file or directory:

1. Identify the file type.
2. Load the matching section in `CODEBASE_RULES.md`.
3. Read `PROJECT_MEMORY.md` for current decisions, review traps, and intentional constraints.
4. Check:
   - file responsibility
   - architectural compliance
   - authorization and nested binding rules for HTTP code
   - query boundaries for tenancy and soft deletes
   - exception and translation contract for business code
   - minimum style expectations
5. List each violation with:
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
- Memory is contextual, not normative: use it to interpret intent, not to override a rule.
