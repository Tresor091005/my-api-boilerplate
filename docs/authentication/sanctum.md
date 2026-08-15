# Authentication with Laravel Sanctum

This document describes the API authentication approach based on Laravel
Sanctum. The project extends Sanctum's basic features to provide a robust,
flexible, and contextual system.

## 1. Philosophy: why Sanctum?

Laravel Sanctum was chosen for its simplicity and effectiveness when managing
API tokens, which is well suited to a stateless API. Although it supports
complex scenarios such as multiple guards, this implementation focuses on a
single `sanctum` guard for clarity while remaining extensible.

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

During login, the token is created and immediately receives the relevant
metadata.

```php
// @app-modules/iam/src/Http/Controllers/AuthController.php
public function login(LoginRequest $request, string $type): JsonResponse
{
    // ... authenticate the user

    $metadata = match ($type) {
        'user'           => ['type' => 'user', 'company_id' => null],
        'company-member' => ['type' => 'agent', 'company_id' => $authenticatable->company_id],
        default          => null,
    };

    // 1. Create the token
    $token = $authenticatable->createToken('auth_token', ['*'], now()->addDay());
    // 2. Update the token with metadata
    $token->accessToken->update(['metadata' => $metadata]);

    return response()->json([
        'access_token' => $token->plainTextToken,
        // ...
    ]);
}
```

## 4. Authentication context (`AuthContext`)

To avoid querying the user or its context repeatedly, the application uses a
scoped `AuthContext` resolved for each authenticated request.

1. **The `AuthContext` class** (`Lahatre\Iam\Auth\AuthContext`) is a simple
   container for the authenticated user and other information such as team and
   role. It is registered as scoped in `IamServiceProvider`.
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

Every route requiring authentication must use this group.

## 6. Configuring authenticatable models

Models that can authenticate, such as `User` or `CompanyMember`, use the
`HasAuthenticatableTraits` trait. It:

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
