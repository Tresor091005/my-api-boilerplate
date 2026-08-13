# Language and documentation conventions

The codebase uses English as its technical language. New or modified source
code, identifiers, comments, PHPDoc, tests, configuration descriptions, and
technical documentation should be written in English. User-facing text is
localized through the English translation tree and the localization rules.

The agent-facing enforcement lives in
`.ai/rules/documentation-and-language.md`; this page gives the human-readable
convention and its documentation boundaries.

## Documentation is part of a code change

After generating code or changing a public or architectural behavior, inspect
what the code actually does and update the nearest authoritative documentation
in the same change. This includes commands and generator contracts, endpoints,
payloads, permissions, cache behavior, queues, mail or realtime communication,
configuration, persistence rules, and operational workflows.

Generated output must be reviewed against the codebase's current conventions;
the existence of a stub or generator does not make its behavior self-evident.
Record non-obvious constraints, side effects, failure modes, and required
options. Update indexes and links when documentation moves, and prefer links to
a single source of truth over duplicated instructions.

Existing historical documents may remain in their original language until they
are intentionally migrated. New and touched content should follow the English
convention without discarding existing information.
