---
name: code-reviewer
description: Code review workflow for this modular Laravel codebase. It must read the central codebase rules before auditing files.
---

# Skill: Code Reviewer

This skill reviews code against the project source of truth.

## Source of Truth

Before any review, always read:

`/Users/imac/Documents/my-api-boilerplate/.agents/CODEBASE_RULES.md`

## Review Process

When asked to review a file or directory:

1. Identify the file type.
2. Load the matching section in `CODEBASE_RULES.md`.
3. Check:
   - file responsibility
   - architectural compliance
   - authorization and nested binding rules for HTTP code
   - exception and translation contract for business code
   - minimum style expectations
4. List each violation with:
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
- Business exceptions are translated through `AssertionException`.
- If a reviewed file contains another language without a strong reason, translate it to English as part of the change.
