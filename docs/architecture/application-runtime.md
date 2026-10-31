# Application runtime and cross-cutting patterns

This page documents behavior that is spread across providers, middleware,
module registration, and shared support classes.

## Request pipeline

`SetLocale` runs first on every request. When `config('app.supported_locales')`
lists the language of the `X-Language` header, the request is handled in that
locale and the previous locale is restored afterwards; otherwise
`config('app.locale')` applies. Translated messages, permission titles, the
built-in role description and currency names follow that locale.

Every API route is in the `api` middleware group. The application appends:

1. `ForceJsonResponse`, which makes API responses JSON-oriented.
2. `throttle:api`, limited to 90 requests per minute per authenticated user or
   IP address.

The `auth.api` group is composed in `bootstrap/app.php` from aliases that the
iam and billing providers register, with their priority. It adds Sanctum
authentication, profile completion, session tracking, `ResolveAuthContext`,
`SetTeamPermissionsId` and
`EnsureSubscriptionAccess` (alias `billing.subscription`), which refuses any request
without a subscription giving access or a running trial (see
[billing](../modules/billing.md#trial-and-access)). It is
required by business module routes.

`ResolveAuthContext` reads the current personal access token metadata and
resolves the selected organization, member, member role, and role. An
incoherent token context raises an authentication error. `SetTeamPermissionsId`
then configures Spatie's permission team to the selected organization and
clears previously loaded role/permission relations.

Middleware priority places `ResolveAuthContext` and `SetTeamPermissionsId`
before `SubstituteBindings`, after Sanctum authentication. Nested model binding
can therefore use organization-scoped relations on the first request, without
depending on an already initialized Spatie team context.

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
generates each alias from the model's singular table name, and registers the
aliases with Eloquent. For example, `catalog_products` maps to
`catalog_product`. Table names must therefore remain unique across models.

`AppServiceProvider` enables `Relation::requireMorphMap(true)`, so every
polymorphic relation must resolve through this registry. Alias collisions are
fatal; the registry never silently replaces an existing alias.

On a normal production application boot, the registry loads
`bootstrap/cache/morph-map.php` when present. In local, testing, staging, and
other non-production environments, the cache is ignored and the registry
always discovers models through `ModelFinder`. Without a production cache, it
also discovers models and registers the generated map. The cache is deployment
state, not business data: regenerate it after adding, removing, renaming, or
moving a model. Renaming a table also renames its morph alias and requires
updating persisted morph types and generated permissions.

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

## Response selection

`ResolveResponseSelection` runs in the `api` group, after authentication, and
validates `response`, `fields` and `include` for the routes whose action
answers through `ApiResponse`; see [response selection](../api/response-selection.md).
Other routes build their own JSON or stream a file.

## Service lifetime

Request-sensitive services such as `AuthContext`, `InventoryInterface`,
`LibraryInterface`, `MasterInterface`, and `UnitCache` are scoped to the application lifecycle.
`OrganizationInterface` is a normal binding. This distinction prevents
organization or request-local state leaking across long-lived workers.

## Model and factory discovery

Models live in `app-modules/<module>/src/Models`. Factories normally live in the
corresponding module test/database structure and are resolved through the
custom factory naming callbacks. `ModelFinder` is also used by morph-map and
architecture tooling to discover module models without a hardcoded model list.
