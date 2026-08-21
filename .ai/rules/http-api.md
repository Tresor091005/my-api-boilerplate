---
paths:
  - routes/**
  - app/Http/**
  - app-modules/*/routes/**
  - app-modules/*/src/Http/Controllers/**
  - app-modules/*/src/Http/Requests/**
  - app-modules/*/src/Http/Resources/**
  - app-modules/*/src/Policies/**
---

# HTTP API Contract

## Routes

- Name module route files `<module>-routes.php`, use the `v1/<module>` URL prefix, and use the `lahatre.<module>.` route-name prefix.
- Apply `api` middleware to API routes and `auth.api` to authenticated routes. Use RESTful, plural, `kebab-case`, action-free URIs and name every route.
- `auth.api` is the project's authenticated API group: it currently applies
  Sanctum authentication, `ResolveAuthContext`, and
  `SetTeamPermissionsId`. Do not duplicate that stack on ordinary protected
  routes.
- There is currently no email-verification middleware in the project. Do not
  document or apply a `verified` middleware until an explicit implementation
  and route policy exist; the IAM routes currently mark that boundary as
  pending.
- Prefer `Route::apiResources()` for simple CRUD groups.
- Apply scoped binding explicitly to every nested resource. A request must never resolve a child that does not belong to the route parent.
- Do not expose a package-generic route in a tenant-scoped host application unless the host access boundary is explicit.

### Response defaults

- Response mode is derived from the HTTP method when a route has a response
  contract: `GET` returns a resource and rejects `response=none`; mutation
  methods (`POST`, `PUT`, and `PATCH`) return `204 No Content` by default and
  may opt into a resource with `?response=resource`; `DELETE` is always
  `204 No Content`.
- A contract may define `default_mode` only for an intentional endpoint
  exception. This override must be documented with the route contract, is not
  allowed for `DELETE`, and must not be added merely to repeat the method
  default.
- Keep `default_shape`, required loads, and allowed includes in the module
  contract. Response mode is not a per-shape configuration concern.

## Controllers

- Keep Controllers limited to HTTP orchestration: authorize, construct Data from validated input, call a service, and return a response.
- Authorize before the service call. For nested reads, authorize the parent with `retrieve`; for nested mutations, authorize the parent with `update`; then authorize the child model or target class for the requested action.
- Do not place business logic or manual validation in Controllers.
- Return `201` for creation, `204` for deletion, and `200` otherwise unless the endpoint contract requires another standard status. Use `response()->noContent()` for responses without a body; do not serialize `null` as JSON for a `204` response.

## Form Requests

- Form Requests own HTTP validation and field-specific normalization. They must not contain Gate or Policy logic.
- Reuse one Request while store and update share a coherent shape; split it when conditional branches or missing-value semantics obscure the contract.
- Normalize only named fields in `prepareForValidation()`; never recursively sanitize every input string.
- Choose presence rules deliberately: absence, `nullable`, `present`, and `required` are different contracts.
- Use `after()` for request-specific cross-field checks. Extract named reusable, nested, or SQL-heavy checks into composable Rules as described in `.ai/rules/validation.md`.
- Use route models for context-sensitive rules such as `unique()->ignore(...)`.

## Policies

- Keep Policies query-free and limited to authorization. Standard abilities are `list`, `retrieve`, `create`, `update`, and `delete`.
- Tenant-owned models must verify `organization_id` as part of model authorization. Return `false` from `restore` and `forceDelete` unless explicitly supported.

## Resources

- Resources transform output only. Add `@mixin`, use `whenLoaded()` for relations, and load required relations before creating the Resource.
- Define scalar fields first, then required loaded relations, then optional
  relations rendered through `includeWhenRequestedAndLoaded()` (or the
  equivalent shared helper). A Resource must never access a relation directly
  to make it available; response shapes and the response context own relation
  loading. Keep every relation guarded by `whenLoaded()` so a loading mistake
  omits the relation instead of causing a lazy-loading query or exception.
- The response context is lifecycle-scoped but not HTTP-only. Middleware
  configures it from query parameters, and the active response shape is the
  only source of required and optional response relation loads. Without an
  active shape, no response relations are loaded; optional includes are added
  only when explicitly requested, and `none` loads no response relations.
- Resource collections extend `Lahatre\Shared\Http\Resources\BaseCollection` and preserve the shared cursor metadata contract.
- Avoid relationship serialization that can recurse indefinitely.

## Middleware and Registration References

- `bootstrap/app.php` defines the `api` group and the `auth.api` middleware
  group, including JSON responses, throttling, Sanctum, authentication
  context, and organization permission context.
- `app/Providers/RateLimitServiceProvider.php` defines the current `api` and
  `auth` limiter values; `config/cache.php` defines the database limiter
  store configuration.
- `app-modules/iam/src/Http/Middleware/ResolveAuthContext.php` resolves the
  authenticated user, organization, member, and role context.
- `app-modules/iam/src/Http/Middleware/SetTeamPermissionsId.php` requires a
  valid organization/member-role context and sets the permission team scope.
- `app-modules/iam/routes/iam-routes.php` shows the distinction between public
  authentication endpoints and authenticated IAM endpoints, including the
  pending email-verification boundary.
- `app-modules/inventory/routes/inventory-routes.php` shows the standard
  module route prefix, naming convention, `api` group, and `auth.api` usage.
- `.ai/rules/validation.md` defines Form Request, Rule, and `after()` usage;
  `.ai/rules/persistence-tenancy.md` defines database tenant boundaries.
- `app-modules/shared/src/Http/Resources/BaseCollection.php` defines the
  shared cursor pagination metadata contract for Resource collections.
- `tests/Feature/RateLimitTest.php` guards route throttling, limiter values,
  and the current limiter-store expectation.
