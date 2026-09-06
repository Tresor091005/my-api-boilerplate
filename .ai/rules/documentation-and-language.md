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

## String transformations

- Prefer the project's registered `Str` and `Stringable` macros for recurring
  string transformations, especially `sanitize()` for trimming leading and
  trailing whitespace and squishing repeated internal whitespace.
- Do not repeatedly combine `trim()` and `squish()` when `sanitize()` expresses
  the intended behavior. Use the more specific macro such as `normalize()`,
  `toTitle()`, `toHeadline()`, `toUpper()`, or `toKebab()` when that complete
  transformation is the intended contract.
- Do not use native `strtoupper()`, `strtolower()`, `Str::upper()`, or
  `Str::lower()` for application string normalization. Use `Str::toUpper()`,
  `Str::normalize()`, or the appropriate registered macro instead. Native
  case conversion remains acceptable for protocol, SQL, parser, and test
  comparisons where application normalization is not the intent.
- Use native `trim()`, `ltrim()`, and `rtrim()` only when removing a syntactic
  boundary such as URL slashes, namespace separators, delimiters, or parser
  whitespace. Use `sanitize()` or another registered macro for business or
  user-input normalization.
- Apply these helpers only to named fields or values whose contract requires
  the transformation; do not recursively rewrite arbitrary input data.

## Documentation after generation or structural change

- For changes to public contracts, architecture, workflows, business rules,
  configuration, commands, generated-file contracts, or non-obvious operational
  behavior, inspect the result and update the nearest existing authoritative
  documentation within scope in the same change.
- Follow `AGENTS.md` for permission to create documentation: create a new file
  only when explicitly requested. If no suitable document exists, include the
  proposed documentation in the handoff without creating a file. Do not add a
  governing rule solely to work around this boundary.
- Record the resulting contract, rationale, ownership boundaries, side effects,
  failure modes, and required commands or migrations when relevant. Pure
  spelling or formatting corrections need no new decision record.
- Update indexes and links when adding, moving, or renaming documentation.
  Do not leave duplicate instructions in multiple locations; link to the
  source of truth instead.
- Before finalizing, check stale references in the changed documentation and
  its direct consumers, validate affected local Markdown links, and run the
  smallest relevant tests or quality checks for the code change.
- When a rule changes how agents or contributors work, update this rules tree
  and affected index entries. When a stable runtime convention changes, update
  relevant existing human documentation as well; link to the source of truth
  instead of duplicating the full explanation.

## Documentation ownership

- `.ai/rules/` contains load-bearing instructions for agents and contributors.
- `docs/architecture/` explains stable conventions and the reasoning behind
  them for human readers.
- `docs/development/` explains workflows, tools, verification, and runtime
  operations.
- A module's `README.md` and `docs/` own module-specific behavior. Keep
  historical decisions in `docs/decisions/` and link them from the stable
  documentation when relevant.
