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
For a model in the configured `Lahatre` module namespace, the alias combines
the module and model names in snake case. This prevents collisions between
models with the same basename in different modules.

`AppServiceProvider` enables `Relation::requireMorphMap(true)`, so every
polymorphic relation must resolve through this registry. Alias collisions are
fatal; the registry never silently replaces an existing alias.

On a normal production application boot, the registry loads
`bootstrap/cache/morph-map.php` when present. In local, testing, staging, and
other non-production environments, the cache is ignored and the registry
always discovers models through `ModelFinder`. Without a production cache, it
also discovers models and registers the generated map. The cache is deployment
state, not business data: regenerate it after adding, removing, renaming, or
moving a model.

Use these commands after adding or renaming a model:

```bash
php artisan morph-map:clear
php artisan morph-map:cache
```

The application optimization lifecycle runs the cache command during optimize
and the clear command during optimize clear. A production deployment should
run the cache command after the new code is available and before workers serve
requests. The architecture test verifies that every concrete model is present
in the active map and reports the model class that is missing.

## Response contract lifecycle

API response contracts follow the same application-owned discovery pattern.
The root API definitions live in `config/response-contracts.php`; each module
that owns API routes may provide `config/response-contracts.php` under its own
module directory. The keys are complete route names, and the values contain
optional shapes, required loads, and allowed includes.

`SharedServiceProvider` discovers these files after providers have registered,
so module providers do not resolve or register the shared registry manually.
The registry rejects duplicate route keys and loads the generated
`bootstrap/cache/response-contracts.php` file only in production. Other
environments always rediscover the configuration. Use:

```bash
php artisan response-contracts:clear
php artisan response-contracts:cache
```

Every application API route must have a contract. Resource-producing routes
declare their shape, required loads, and allowed includes. An empty definition
is reserved for an endpoint with no response representation, such as a
deletion, and must have an inline explanation in the owning module config. An
empty contract still supplies the method-derived response policy:
GET returns a resource, POST/PUT/PATCH return no content by default, and DELETE
is always no content. The architecture test reports both missing route
contracts and stale contract keys. The HTTP response-context middleware also
fails before controller execution when a routed API endpoint has no contract.
Response mode overrides are exceptional and must be documented with the route.

`ResponseContext` is scoped to the application lifecycle, not exclusively to
HTTP requests. `ResolveResponseContext` is the HTTP adapter that configures it
from query parameters. The active response shape is the only source of
required and optional response relation loads; without an active shape, no
response relations are loaded. Optional includes are loaded only when
requested, and `response=none` loads no response relations.

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
