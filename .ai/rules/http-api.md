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

## Controllers

- Keep Controllers limited to HTTP orchestration: authorize, construct Data from validated input, call a service, and return a response.
- Authorize before the service call. For nested reads, authorize the parent with `retrieve`; for nested mutations, authorize the parent with `update`; then authorize the child model or target class for the requested action.
- Do not place business logic or manual validation in Controllers.
- Return `201` for creation, `204` for deletion, and `200` otherwise unless the endpoint contract requires another standard status.

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
- Resource collections extend `Lahatre\Shared\Http\Resources\BaseCollection` and preserve the shared cursor metadata contract.
- Avoid relationship serialization that can recurse indefinitely.

## Middleware and Registration References

- `bootstrap/app.php` defines the `api` group and the `auth.api` middleware
  group, including JSON responses, throttling, Sanctum, authentication
  context, and organization permission context.
- `app/Providers/RateLimitServiceProvider.php` defines the current `api` and
  `auth` limiter values; `config/cache.php` defines the dedicated limiter
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
