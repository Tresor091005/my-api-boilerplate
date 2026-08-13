---
paths:
  - app-modules/**
---

# Modular Architecture

## Module Boundary

- Treat `app-modules/<module>` as the unit of ownership for its source, routes, migrations, factories, seeders, translations, and module-local tests.
- Use the `Lahatre\<Module>\` namespace and the module's Composer autoload mapping. Do not place module code under the root `App\` namespace.
- Module names are lowercase and filesystem/package-safe; namespaces use the corresponding `StudlyCase` name. Preserve this mapping in folders, Composer packages, namespaces, route prefixes, and translation namespaces.
- Keep the established `src`, `routes`, `database`, `resources`, and `tests` structure. Optional `config` and `docs` directories are allowed when the module needs them; do not add another base directory without approval.
- A new module must provide Composer package metadata and PSR-4 mapping, a module ServiceProvider, and the standard structure required by its responsibilities. Let Internachi/Modular and Laravel conventions discover routes, resources, translations, migrations, and other standard package concerns; use the provider for bindings, config merging, and genuinely non-inferable integration.
- Respect the explicit acyclic module dependency graph. A reusable module must not import an unrelated host module merely to simplify a test or implementation.
- Cross-module consumers should use the owning module's public Contracts, interfaces, or documented services. Do not depend on undocumented implementation details or modify another module's tables, migrations, models, translations, or seeders directly.
- Database objects belong to the module that owns the related domain. Cross-module corrections go through the owning module's migration directory or a public integration contract, never through a foreign module's migrations.

## Generation Workflow

- Generate module files with the modular Artisan command and `--module=<module>`, for example `php artisan make:controller ProductController --module=catalog --no-interaction`.
- Create a module with `php artisan make:module <module> --no-interaction`, then register or refresh its path dependency through Composer.
- After structural changes, verify autoloading and inspect the module routes with `php artisan route:list --path=v1/<module>`.
- All module-aware `make:*` commands must receive `--module=<module>`; verify
  the command's help output before relying on a less common generator option.
  This applies to controllers, models, migrations, requests, classes, and
  other generators that expose the option.
- Seed only the intended module with `php artisan db:seed --module=<module>`;
  use `--class=<Seeder>` when targeting one module seeder. Do not run a broad
  seeder when a module-scoped command is sufficient.
- After creating or changing a module, run `composer dump-autoload`, inspect
  `php artisan modules:list` when discovery is relevant, and inspect
  `php artisan route:list --path=v1/<module>` for HTTP modules.

## Responsibility Map

- Controllers orchestrate HTTP concerns; services orchestrate business behavior.
- Form Requests validate and normalize HTTP input; Data classes transport typed values to services.
- Policies decide HTTP permissions; assertions enforce reusable business invariants.
- Models represent persistence; Resources transform output; Providers wire the module.
- Cross-module HTTP authorization belongs to application integration tests. Module-local tests cover the module's own business contract.
- Module-local tests cover the module's business contract; cross-module authorization, real HTTP boundaries, and dependency behavior belong in application integration or architecture tests.

## Working Standard

- Write code, comments, PHPDoc, test names, messages, and rule text in English.
- Prefer explicit, readable code with complete return types. Add PHPDoc when native signatures cannot express the shape.
- Run the smallest relevant tests, then use `composer quality:check` as the non-mutating full quality gate when the scope warrants it. Use `composer quality` only when automated Rector/Pint changes are intended.

Detailed normative definitions live in `.ai/rules/`; universal invariants live
in `.agents/CODEBASE_RULES.md`; evolving decisions and exceptions remain in
`.agents/PROJECT_MEMORY.md`.

## References

- `app-modules/inventory/` is a complete module example with source, routes,
  database, resources, and tests.
- `app-modules/inventory/src/Providers/InventoryServiceProvider.php` shows a
  provider limited to configuration merging and a public binding.
- `.ai/rules/http-api.md` defines module route and HTTP boundary conventions;
  `.ai/rules/localization.md` defines translation ownership and structure.
