---
name: code-generator
description: Code generation workflow for this modular Laravel codebase. It must read the central codebase rules and project memory before generating anything.
---

# Skill: Code Generator

This skill generates code by applying the project source of truth and current project memory.

## Source of Truth

Before generating anything, always read:

`/Users/imac/Documents/my-api-boilerplate/.agents/CODEBASE_RULES.md`
`/Users/imac/Documents/my-api-boilerplate/.agents/PROJECT_MEMORY.md`

## Mission

- Identify the file type to create or modify.
- Load the matching section from `CODEBASE_RULES.md`.
- Read `PROJECT_MEMORY.md` for current architectural decisions, review traps, and local constraints.
- Produce a file that strictly follows the rules for responsibility, style, authorization, exceptions, routes, localization, and tests.
- When rules and memory differ in nature, treat rules as normative and memory as contextual.

## Operating Rules

1. Determine the target file type.
2. Read the matching section in `CODEBASE_RULES.md`.
3. Read the relevant notes in `PROJECT_MEMORY.md`.
4. Generate the smallest complete implementation.
5. If the file conventionally implies other layers, propose or generate them too:
   - exception + translation
   - nested route + scoped binding
   - controller + policy + service + test
6. If a rule is missing, flag the gap and propose a `CODEBASE_RULES.md` or `PROJECT_MEMORY.md` update first, depending on whether the point is stable or contextual.
