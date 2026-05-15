---
name: code-generator
description: Code generation workflow for this modular Laravel codebase. It must read the central codebase rules before generating anything.
---

# Skill: Code Generator

This skill generates code by applying the project source of truth.

## Source of Truth

Before generating anything, always read:

`/Users/imac/Documents/my-api-boilerplate/.agents/CODEBASE_RULES.md`

## Mission

- Identify the file type to create or modify.
- Load the matching section from `CODEBASE_RULES.md`.
- Produce a file that strictly follows the rules for responsibility, style, authorization, exceptions, routes, localization, and tests.
- When in doubt, follow `CODEBASE_RULES.md` instead of habit.

## Operating Rules

1. Determine the target file type.
2. Read the matching section in `CODEBASE_RULES.md`.
3. Generate the smallest complete implementation.
4. If the file conventionally implies other layers, propose or generate them too:
   - exception + translation
   - nested route + scoped binding
   - controller + policy + service + test
5. If a rule is missing, flag the gap and propose a `CODEBASE_RULES.md` update first.
