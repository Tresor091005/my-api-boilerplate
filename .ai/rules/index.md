# Project Rules Index

Before planning or editing, find every row whose globs match the files in scope and read all of those rule files. Overlap is intentional: a service usually matches both domain and persistence rules.

Authority order:

1. `.ai/rules/*.md` is the normative detailed source for the matching path or domain.
2. `.agents/CODEBASE_RULES.md` contains only universal invariants and conflict-resolution rules.
3. `.agents/PROJECT_MEMORY.md` explains current decisions, exceptions, and review traps; it does not override rules.
4. Existing code is supporting evidence, not permission to repeat a known violation.

When sources conflict, stop and reconcile the appropriate rule file before recording a new convention. Search `.ai/rules` by domain keyword as well as by path because some cross-cutting constraints are easy to miss.

| Applies to | Rule file |
| --- | --- |
| app-modules/*/src/Services/**, app-modules/*/src/Data/**, app-modules/*/src/Assertions/**, app-modules/*/src/Exceptions/**, app-modules/*/src/ViewData/** | .ai/rules/domain-services-data.md |
| app-modules/*/src/Providers/**, app-modules/*/src/Support/**, app-modules/*/src/Integrations/**, app-modules/*/src/Contracts/**, app-modules/*/src/Registries/**, app-modules/*/src/Traits/** | .ai/rules/extension-points.md |
| routes/**, app/Http/**, app-modules/*/routes/**, app-modules/*/src/Http/Controllers/**, app-modules/*/src/Http/Requests/**, app-modules/*/src/Http/Resources/**, app-modules/*/src/Policies/** | .ai/rules/http-api.md |
| app-modules/inventory/** | .ai/rules/inventory-tenancy.md |
| app/**, app-modules/*/resources/lang/**, app-modules/*/src/**, app-modules/*/database/**, routes/** | .ai/rules/localization.md |
| app-modules/** | .ai/rules/module-architecture.md |
| app/**, app-modules/*/src/**, app-modules/*/database/**, database/** | .ai/rules/persistence-tenancy.md |
| tests/**, app-modules/*/tests/** | .ai/rules/testing.md |
| app-modules/*/src/Rules/**, app-modules/*/src/Validation/**, app-modules/*/src/Http/Requests/** | .ai/rules/validation.md |
