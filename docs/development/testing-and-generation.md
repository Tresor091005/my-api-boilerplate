# Testing and code generation

## Test execution

Tests use Pest and `RefreshDatabase`. In the current development workflow,
PHPUnit points directly at the single Docker PostgreSQL database
`my_api_boilerplate`; running the suite is intentionally destructive to that
database. Do not run the suite against valuable data.

Run inside Docker:

```bash
make test
docker compose exec -T app php artisan test --compact
```

Module-local tests cover service, data, assertion, and persistence behavior.
Root integration tests cover authentication, authorization, middleware,
tenancy, and HTTP boundaries. Architecture tests guard conventions such as
module dependencies, route names, model integrity, morph maps, and generator
output.

## Quality checks

`composer quality:check` runs Rector dry-run, Pint check, and PHPStan. The
pre-commit hook currently runs Pint's mutating formatter; it does not replace
the complete quality gate.

## Generator contract

Native Laravel `make:*` commands are extended by `SharedServiceProvider` for
classes, controllers, enums, interfaces, job middleware, models, scopes,
traits, tests, and views. Module-aware generators accept `--module=<module>`.
Custom shared commands also generate services, policies, resources, and
collections. Their output is governed by `stubs/`, `.agents/skills/code-generator/stubs/`,
and `app-modules/shared/stubs/`, with command placeholders kept synchronized.

When adding a new generated file type, update the command, stub, generator
architecture test, and the relevant module documentation together.
