---
name: code-generator
description: Code generation workflow for this modular Laravel codebase. It must read the central codebase rules and project memory before generating anything.
---

# Skill: Code Generator

This skill generates code by applying the project source of truth and current project memory. The `.ai/rules` tree is the working navigation layer for that source of truth.

## Source of Truth

Before generating anything, always read:

`.ai/rules/index.md`
`.agents/CODEBASE_RULES.md`
`.agents/PROJECT_MEMORY.md`

## Mission

- Identify the file type to create or modify.
- Load every matching rule from `.ai/rules/` through the index.
- Read `PROJECT_MEMORY.md` for current architectural decisions, review traps, and local constraints.
- Produce a file that strictly follows the rules for responsibility, style, authorization, exceptions, routes, localization, and tests.
- Treat `.ai/rules/` as normative, `CODEBASE_RULES.md` as universal guardrails,
  and `PROJECT_MEMORY.md` as contextual.

## Operating Rules

1. Determine the target file type and every matching row in `.ai/rules/index.md`.
2. Read every matching `.ai/rules/*.md` file, then the global invariants in `CODEBASE_RULES.md`.
3. Read the relevant notes in `PROJECT_MEMORY.md`.
4. Generate the smallest complete implementation.
5. If the file conventionally implies other layers, propose or generate them too:
   - exception + translation
   - nested route + scoped binding
   - controller + policy + service + test
6. Use only the examples referenced by the matching rules; do not scan the whole examples directory.
7. If a rule is missing, flag the gap and propose the appropriate `.ai/rules/*.md`, `CODEBASE_RULES.md`, or `PROJECT_MEMORY.md` update before generation.

## Reference Examples

- Read only the example files referenced by the matching `.ai/rules` section for the file type in scope.
- Examples illustrate collaboration and documentation; rules remain authoritative when an example and a rule diverge.

## Template Locations

- `stubs/` contains project-wide overrides for Laravel's native generators
  (models, persistence files, requests, controllers, and Pest tests). Treat
  these as the default output contract for standard `make:*` commands.
- .agents/skills/code-generator/stubs/ contains agent-facing implementation
  patterns. Complete them with the target model's actual fields, relations,
  translation keys, tenant boundary, and service output before presenting code.
- app-modules/shared/stubs/ contains templates consumed by custom Artisan
  generators. Keep placeholders synchronized with the corresponding Make*
  command; do not add placeholders that the command does not replace.
- Do not copy domain fields such as name, handle, or organization_id into
  a generic template unless the target contract explicitly requires them.
