---
name: code-generator
description: Implement or modify application code in this modular Laravel repository using its module conventions and targeted verification. Use for requested implementation, not review-only or explanatory tasks.
---

# Skill: Code Generator

## Operating Rules

1. Identify the requested behavior and affected paths. Read `.ai/rules/index.md`,
   `.agents/CODEBASE_RULES.md`, and every matching rule. Reuse unchanged readings.
2. Inspect relevant sibling files and consumers. Follow the global invariants
   for source authority, missing conventions, and unresolved conflicts.
3. Generate the smallest complete implementation, including other layers only
   when necessary for the requested contract. Follow `AGENTS.md` and the module
   rules for Artisan generation; use the matching HTTP and validation rules for
   Form Request naming and shape.
4. Read only examples referenced by the relevant rule sections. Examples
   illustrate implementation; they do not override rules.
5. Inspect the resulting diff and follow `.ai/rules/testing.md` for validation,
   failures, and completion. Update relevant documentation under
   `.ai/rules/documentation-and-language.md`.

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
