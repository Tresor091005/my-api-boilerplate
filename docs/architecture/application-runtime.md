# Application runtime and cross-cutting patterns

This page documents behavior that is spread across providers, middleware,
module registration, and shared support classes.

## Request pipeline

Every API route is in the `api` middleware group. The application appends:

1. `ForceJsonResponse`, which makes API responses JSON-oriented.
2. `throttle:api`, limited to 90 requests per minute per authenticated user or
   IP address.

The `auth.api` group adds Sanctum authentication, `ResolveAuthContext`, and
`SetTeamPermissionsId`. It is required by business module routes.

`ResolveAuthContext` reads the current personal access token metadata and
resolves the selected organization, member, member role, and role. An
incoherent token context raises an authentication error. `SetTeamPermissionsId`
then configures Spatie's permission team to the selected organization and
clears previously loaded role/permission relations.

Authentication routes that only need a user use `auth:sanctum` and
`ResolveAuthContext`. Routes that authorize organization-scoped behavior use
the complete `auth.api` group.

## Error contracts

- `ValidationException` returns HTTP 422 with `message: Validation failed` and
  an `errors` object keyed by validated input paths.
- `AssertionException` returns HTTP 422 with `message`, an exception `type`,
  and non-production context when available.
- JSON `AccessDeniedHttpException` returns HTTP 403 with a `message`.
- Non-JSON requests keep Laravel's normal exception rendering.

## Eloquent defaults

`AppServiceProvider` applies these global defaults:

- dates use `CarbonImmutable`;
- Eloquent strict mode is enabled outside production, exposing lazy-loading,
  missing-attribute, and discarded-attribute mistakes during development and
  tests;
- every polymorphic relation requires a registered morph map;
- factory/model discovery supports `App\Models` and module `Models`
  namespaces;
- shared string macros normalize, sanitize, title-case, headline-case, and
  kebab-case values.

## Morph-map lifecycle

`MorphMapRegistry` discovers concrete models in the application and modules,
generates aliases such as `catalog_product`, and registers them with Eloquent.
It loads `bootstrap/cache/morph-map.php` when present.

Use these commands after adding or renaming a model:

```bash
php artisan morph-map:clear
php artisan morph-map:cache
```

The application optimization lifecycle runs the cache command during optimize
and the clear command during optimize clear. Alias collisions are fatal and
must be resolved instead of silently overwritten.

## Service lifetime

Request-sensitive services such as `AuthContext`, `InventoryInterface`,
`MasterInterface`, and `UnitCache` are scoped to the application lifecycle.
`OrganizationInterface` is a normal binding. This distinction prevents
organization or request-local state leaking across long-lived workers.

## Model and factory discovery

Models live in `app-modules/<module>/src/Models`. Factories normally live in the
corresponding module test/database structure and are resolved through the
custom factory naming callbacks. `ModelFinder` is also used by morph-map and
architecture tooling to discover module models without a hardcoded model list.
