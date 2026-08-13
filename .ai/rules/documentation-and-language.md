---
paths:
  - "**"
---

# Codebase Language and Documentation

## Language

- Write new or modified source code, comments, PHPDoc, test names, fixtures,
  configuration descriptions, commit-facing developer messages, and technical
  documentation in English.
- Keep identifiers, class names, method names, database objects, API keys, and
  machine-readable values in English and consistent with the existing naming
  conventions.
- User-facing API, validation, exception, mail, notification, and CLI text
  must come from the English translation tree. Do not hardcode display text in
  application code; follow `localization.md` for translation ownership.
- Existing non-English documentation does not need an opportunistic rewrite.
  When editing such a document, prefer migrating the touched content to
  English when that can be done without losing information.

## Documentation after generation or structural change

- After generating or materially changing code, inspect the resulting behavior
  and update the relevant documentation in the same change when the public
  surface, architecture, workflow, business rule, configuration, command,
  generated-file contract, or operational behavior changed.
- Generated files are not self-documenting. Record non-obvious choices,
  required options, ownership boundaries, side effects, failure modes, and
  examples in the nearest authoritative document.
- Update indexes and links when adding, moving, or renaming documentation.
  Do not leave duplicate instructions in multiple locations; link to the
  source of truth instead.
- Before finalizing, search for stale paths, old command names, outdated ports,
  obsolete package references, and claims contradicted by the current code.
  Validate local Markdown links and run the smallest relevant tests or quality
  checks for the code change.

## Documentation ownership

- `.ai/rules/` contains load-bearing instructions for agents and contributors.
- `docs/architecture/` explains stable conventions and the reasoning behind
  them for human readers.
- `docs/development/` explains workflows, tools, verification, and runtime
  operations.
- A module's `README.md` and `docs/` own module-specific behavior. Keep
  historical decisions in `docs/decisions/` and link them from the stable
  documentation when relevant.
