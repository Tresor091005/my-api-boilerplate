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
- `auth.platform` is the group for platform operator routes. It applies
  Sanctum authentication, profile completion, session tracking,
  `ResolveAuthContext:user`, then `EnsurePlatformAccess`, which answers 403
  unless the token is marked platform (`POST /v1/auth/switch-platform`) and
  its user is still in `iam_platform_users`. A token works at one level only:
  a platform token has no organization context, so `auth.api` routes deny it,
  and an organization-context token is denied on `auth.platform` routes.
  Platform membership is managed only through `iam:platform-access`, never
  through the API.
- IAM organization registration is public and requires a single-use token sent
  to the supplied email before a user or organization is created. The emailed
  link targets the configured frontend URL; its display-only account flag must
  never determine server-side ownership or validation.
- Prefer `Route::apiResources()` for simple CRUD groups.
- Apply scoped binding explicitly to every nested resource. A request must never resolve a child that does not belong to the route parent.
- Do not expose a package-generic route in a tenant-scoped host application unless the host access boundary is explicit.

### Response defaults

- Every route rendering a model answers through the response selection
  package (`packages/response-selection`, see its README). A route that
  renders no model (a file download, the IAM sign-in payload and messages, the
  inventory lots and stock summary) builds its JSON in the controller, always
  with a body, or streams a file.
- Response mode is derived from the HTTP method: `GET` returns a resource and rejects `response=none`; mutation
  methods (`POST`, `PUT`, and `PATCH`) return `204 No Content` by default and
  may opt into a resource with `?response=resource`; `DELETE` is always
  `204 No Content`.
- Response selection has no per-route default mode.

## Controllers

- Keep Controllers limited to HTTP orchestration: authorize, construct Data from validated input, call a service, and return a response.
- Authorize before the service call. For nested reads, authorize the parent with
  `retrieve`; for nested mutations, authorize the parent with `update` or with
  the parent capability that owns the nested operation. A child model or target
  class receives a separate authorization only when it is an autonomous
  business resource with its own permission boundary. Sub-resources that are
  extensions, projections, or technical records of the parent use the parent's
  authorization alone.
- Do not place business logic or manual validation in Controllers.
- IAM `AuthService` accepts the IAM `User` model explicitly. Preserve the
  runtime assertions in `AuthController` before service calls. Multi-guard or
  multi-authenticatable support requires deliberately widening that contract,
  not merely removing the assertions.
- Return `201` for creation, `204` for deletion, and `200` otherwise unless the endpoint contract requires another standard status. Do not serialize `null` as JSON for a `204` response.
- Every action rendering a model ends with one `ApiResponse` call written in the action body
  (`item()`, `list()`, `cursorPage()` or `noContent()`), with a literal
  `XResource::class` and a literal status. A route rendering no model returns
  `response()->json()` or a `JsonResource`'s `response()` with an explicit
  status when it is not 200.

## Form Requests

- Form Requests own HTTP validation and field-specific normalization. They must not contain Gate or Policy logic.
- Injected Form Requests validate before Controller authorization. An
  authenticated but unauthorized request may therefore receive validation
  errors before the Controller Gate runs. This is an accepted ordering
  tradeoff; retain route rate limiting and Controller authorization.
- Reuse one Request while store and update share a coherent shape; split it when conditional branches or missing-value semantics obscure the contract.
- When create and update require separate Requests, name them `EntityCreateRequest` and `EntityUpdateRequest`, with the resource name first. Do not introduce `StoreEntityRequest` or `UpdateEntityRequest`.
- Normalize only named fields in `prepareForValidation()`; never recursively sanitize every input string.
- Choose presence rules deliberately: absence, `nullable`, `present`, and `required` are different contracts.
- Use `after()` for request-specific cross-field checks. Extract named reusable, nested, or SQL-heavy checks into composable Rules as described in `.ai/rules/validation.md`.
- Use route models for context-sensitive rules such as `unique()->ignore(...)`.

## Policies

- Keep Policies query-free and limited to authorization. Standard abilities are `list`, `retrieve`, `create`, `update`, and `delete`.
- Tenant-owned models must verify `organization_id` as part of model authorization. Return `false` from `restore` and `forceDelete` unless explicitly supported.
- Model permissions use the registered morph alias, not the model basename
  (for example, `catalog_product.retrieve`). `MorphMapRegistry` owns model
  identity for discovery and dynamic polymorphic authorization. Policies use
  `BasePolicy::canModel()` or `canOnModel()` instead of hardcoded prefixes;
  missing permissions deny access. When this convention changes, update
  `docs/iam/permissions.md` and this rule together.
- Platform policies authorize with the `operate-platform` Gate, never with
  `canModel()`: organization permissions, including every permission of the
  Administrator role, never grant platform access. The Gate reads the platform
  access `EnsurePlatformAccess` confirmed in `AuthContext`, so it stays
  query-free. Add granular platform abilities beside that Gate, not in routes
  or controllers.

## Resources

- A Resource rendering a model extends `ApiResource` and only declares its members in `define()`: each field lists
  what it reads (`columns:`, `relations:`, `fields:`), relations are
  `include()`, children built in code are `virtualInclude()`, declared in that
  order: fields, then includes, then virtual includes. Do not write
  `toArray()`, `whenLoaded()` or Collection classes. A child from another
  module is rendered by that module's own Resource. A costly field few clients
  need is declared `default: false`. A list include keeps the default limit; it
  declares a higher `limit:` only when the list is bounded by design (role
  permissions grow with the code), with a test proving the whole list fits.
  Test a Resource's output through HTTP or the package's
  `RendersApiResources` helper, not by building it directly.
- A plain `JsonResource` renders only an output that is not a model (such as
  `AuthResource` and `InventoryLotResource`). It transforms output only: the
  controller or service loads what it shows, and it guards relations with
  `whenLoaded()` so a loading mistake omits them instead of querying.
- A collection of such rows extends `Lahatre\Shared\Http\Resources\BaseCollection` and preserves the shared cursor metadata contract.
- Avoid relationship serialization that can recurse indefinitely.

## Middleware and Registration References

- `bootstrap/app.php` defines the `api` group and the `auth.api` middleware
  group, including JSON responses, throttling, Sanctum, authentication
  context, and organization permission context. It prepends `SetLocale`
  globally: the `X-Language` header picks a locale among
  `app.supported_locales` for the duration of the request, else `app.locale`
  applies.
- `app/Providers/RateLimitServiceProvider.php` defines the current `api` and
  `auth` limiter values; `config/cache.php` defines the database limiter
  store configuration.
- `app-modules/iam/src/Http/Middleware/ResolveAuthContext.php` resolves the
  authenticated user, organization, member, and role context.
- `app-modules/iam/src/Http/Middleware/EnsurePlatformAccess.php` checks the
  platform token marker and the platform membership once per request.
- `app-modules/iam/src/Http/Middleware/SetTeamPermissionsId.php` requires a
  valid organization/member-role context and sets the permission team scope.
- `app-modules/iam/routes/iam-routes.php` shows the distinction between public
  authentication endpoints and authenticated IAM endpoints.
- `app-modules/inventory/routes/inventory-routes.php` shows the standard
  module route prefix, naming convention, `api` group, and `auth.api` usage.
- `.ai/rules/validation.md` defines Form Request, Rule, and `after()` usage;
  `.ai/rules/persistence-tenancy.md` defines database tenant boundaries.
- `app-modules/shared/src/Http/Resources/BaseCollection.php` defines the
  shared cursor pagination metadata contract for Resource collections.
- `tests/Feature/RateLimitTest.php` guards route throttling, limiter values,
  and the current limiter-store expectation.
