# Authentication with Laravel Sanctum

This document describes the API authentication approach based on Laravel
Sanctum. The project extends Sanctum's basic features to provide a robust,
flexible, and contextual system.

## 1. Philosophy: why Sanctum?

Laravel Sanctum was chosen for its simplicity and effectiveness when managing
API tokens, which is well suited to a stateless API. This implementation uses
the `sanctum` guard for IAM users.

## 2. Token extension: adding metadata

The Sanctum `PersonalAccessToken` model is extended to enrich each token's
context.

### Migration

A migration adds a nullable `jsonb` `metadata` column to
`personal_access_tokens`.

```php
// @app-modules/iam/database/migrations/..._add_metadata_to_personal_access_tokens.php
Schema::table('personal_access_tokens', function (Blueprint $table): void {
    $table->jsonb('metadata')->nullable();
});
```

### Custom model

`Lahatre\Iam\Auth\PersonalAccessToken` extends Sanctum's model. It casts
`metadata` to `json` and adds a `getMeta()` helper.

The custom model is registered in `IamServiceProvider`:

```php
// @app-modules/iam/src/Providers/IamServiceProvider.php
public function boot(): void
{
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
}
```

## 3. Login flow

`POST /v1/auth/login` checks the IAM user's email and password and returns a
one-day bearer token. Its `organization_id`, `member_id`, `member_role_id`, and
`role_id` metadata are initially null. The response lists the user's member
roles so the client can choose an organization and role.

`POST /v1/auth/switch-member-role` accepts a `member_role_id`. It verifies that
the role belongs to the authenticated user's membership and that the membership
and role refer to the same organization. On success, it stores the selected
organization, member, member role, and role IDs in the current token. A rejected
selection leaves the token unchanged.

Memberships and member roles use soft deletes. An active member role is unique
for its organization, member, and role; a deleted assignment may be followed
by a new assignment with a new ID.

## 4. Authentication context (`AuthContext`)

To avoid querying the user or its context repeatedly, the application uses a
scoped `AuthContext` resolved for each authenticated request.

1. **The `AuthContext` class** (`Lahatre\Iam\Auth\AuthContext`) holds the
   authenticated user, organization, membership, member role, and role. It is
   registered as scoped in `IamServiceProvider`. For a selected role, it checks
   the token IDs against the active member role and requires the membership to
   belong to both the user and the same organization. An incoherent context
   rejects the request.
2. **The `ResolveAuthContext` middleware** populates the scoped context with
   the current user's information on every authenticated request.
3. **The `authContext()` helper** in `app-modules/shared/src/helpers.php` gives
   the application a readable global access point:

   ```php
   function authContext(): AuthContext
   {
       return app(AuthContext::class);
   }
   ```

   It can then be used as `authContext()->user()`.

## 5. Applying authentication to routes

`bootstrap/app.php` defines an `auth.api` middleware group that applies the
Sanctum guard and the context-resolution middleware together.

```php
$middleware->group('auth.api', [
    'auth:sanctum',
    ResolveAuthContext::class,
    SetTeamPermissionsId::class,
]);
```

Tenant-scoped protected routes use this group. The IAM `me`, `logout`, and
`switch-member-role` routes use Sanctum and `ResolveAuthContext` without
requiring an active organization yet.

## 6. Configuring authenticatable models

The IAM `User` extends the shared `Authenticatable` base class. It:

1. Includes Sanctum's `HasApiTokens`.
2. Includes Spatie's `HasRoles`.
3. Defines `protected string $guard_name = 'sanctum';`, forcing
   `spatie/laravel-permission` to use the same guard as Sanctum.

## 7. Environment configuration

To make Sanctum the default authentication mechanism for API guards, define
the following variable in `.env.example`:

```dotenv
AUTH_GUARD=sanctum
```

Laravel then uses the `sanctum` driver for the default `api` guard.
