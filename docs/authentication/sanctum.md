# Authentification via Laravel Sanctum

Ce document détaille l'approche d'authentification de l'API, qui repose sur Laravel Sanctum. Nous avons étendu ses fonctionnalités de base pour créer un système robuste, flexible et contextuel.

## 1. Philosophie : Pourquoi Sanctum ?

Laravel Sanctum a été choisi pour sa simplicité et son efficacité dans la gestion de l'authentification par tokens (API tokens), ce qui est parfait pour une API stateless. Bien qu'il supporte des scénarios complexes (multi-guards), notre implémentation se concentre sur un seul `guard` (`sanctum`) pour plus de clarté, tout en restant extensible.

## 2. Extension du Token : Ajout de Métadonnées

Pour enrichir le contexte de chaque token, nous avons étendu le modèle `PersonalAccessToken` de Sanctum.

### Migration
Une migration ajoute une colonne `metadata` de type `jsonb` à la table `personal_access_tokens`.
```php
// @app-modules/iam/database/migrations/..._add_metadata_to_personal_access_tokens.php
Schema::table('personal_access_tokens', function (Blueprint $table): void {
    $table->jsonb('metadata')->nullable();
});
```

### Modèle Personnalisé
Nous avons créé un modèle `Lahatre\Iam\Auth\PersonalAccessToken` qui étend celui de Sanctum. Il caste la colonne `metadata` en `json` et ajoute un helper `getMeta()`.

Ce modèle personnalisé est ensuite enregistré dans le `IamServiceProvider` :
```php
// @app-modules/iam/src/Providers/IamServiceProvider.php
public function boot(): void
{
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
}
```

## 3. Flux de Connexion (Login)

Lors de la connexion, nous créons le token puis nous y attachons immédiatement les métadonnées pertinentes.

```php
// @app-modules/iam/src/Http/Controllers/AuthController.php
public function login(LoginRequest $request, string $type): JsonResponse
{
    // ... (authentification de l'utilisateur)

    $metadata = match ($type) {
        'user'           => ['type' => 'user', 'company_id' => null],
        'company-member' => ['type' => 'agent', 'company_id' => $authenticatable->company_id],
        default          => null,
    };

    // 1. Crée le token
    $token = $authenticatable->createToken('auth_token', ['*'], now()->addDay());
    // 2. Met à jour le token avec les métadonnées
    $token->accessToken->update(['metadata' => $metadata]);

    return response()->json([
        'access_token' => $token->plainTextToken,
        // ...
    ]);
}
```

## 4. Le Contexte d'Authentification (`AuthContext`)

Pour éviter de requêter l'utilisateur ou son contexte à de multiples endroits, nous utilisons un singleton `AuthContext` qui est résolu pour chaque requête authentifiée.

1.  **La Classe `AuthContext`** (`Lahatre\Iam\Auth\AuthContext`): C'est un simple conteneur pour l'utilisateur authentifié et d'autres informations (équipe, rôle, etc.). Il est enregistré en tant que `scoped` dans `IamServiceProvider`.

2.  **Le Middleware `ResolveAuthContext`**: Ce middleware, appliqué à chaque requête authentifiée, est responsable de peupler le singleton `AuthContext` avec les informations de l'utilisateur courant.

3.  **Le Helper `authContext()`**: Un helper global (`app-modules/shared/src/helpers.php`) a été créé pour un accès facile et lisible à l'instance de `AuthContext` partout dans l'application.
    ```php
    // @app-modules/shared/src/helpers.php
    function authContext(): AuthContext
    {
        return app(AuthContext::class);
    }
    ```
    On peut ensuite l'utiliser très simplement : `authContext()->user()`.

## 5. Application aux Routes

Dans `bootstrap/app.php`, un groupe de middlewares nommé `auth.api` a été défini. Il applique à la fois le guard de Sanctum et notre middleware de résolution de contexte.
```php
// @bootstrap/app.php
$middleware->group('auth.api', [
    'auth:sanctum',
    ResolveAuthContext::class,
    SetTeamPermissionsId::class,
]);
```
Toutes les routes nécessitant une authentification doivent utiliser ce groupe.

## 6. Configuration des Modèles "Authentifiables"

Les modèles qui peuvent s'authentifier (comme `User` ou `CompanyMember`) utilisent le trait `HasAuthenticatableTraits`. Ce trait est crucial car :
1.  Il inclut `HasApiTokens` de Sanctum.
2.  Il inclut `HasRoles` de Spatie.
3.  Il définit `protected string $guard_name = 'sanctum';`, forçant `spatie/laravel-permission` à utiliser le même guard que Sanctum, ce qui garantit une intégration parfaite des rôles et permissions.

## 7. Configuration d'Environnement

Enfin, pour que Sanctum soit le mécanisme d'authentification par défaut pour les gardes API, la variable suivante est définie dans `.env.example` :
```dotenv
AUTH_GUARD=sanctum
```
Cela indique à Laravel d'utiliser le pilote `sanctum` pour le guard `api` par défaut.
