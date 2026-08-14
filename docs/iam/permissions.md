# Roles and Permissions with Spatie

This project uses `spatie/laravel-permission` as the foundation for a
team-aware role and permission system. This document describes the
project-specific conventions and runtime behavior.

## 1. Philosophie et Objectifs

-   **UUIDs par défaut :** Toutes les clés primaires et étrangères utilisent des UUIDs.
-   **Contextualisation par équipe :** Le système est conçu pour être "multi-tenant" au niveau des permissions. Chaque utilisateur opère dans le contexte d'une équipe (`team_id`).
-   **Permissions système ("Built-in") :** Un mécanisme permet de découvrir et d'enregistrer des permissions et des rôles système qui ne sont pas modifiables par les utilisateurs, garantissant une base stable.
-   **Simplicité de nommage :** Les permissions suivent une convention simple et prévisible : `[modèle].[action]` (ex: `posts.create`).

## 2. Configuration (`permission.php`)

Le fichier de configuration `@app-modules/iam/config/permission.php` a été ajusté pour nos besoins :

-   **Modèles personnalisés :** Il utilise nos propres modèles `Lahatre\Iam\Models\Permission` et `Lahatre\Iam\Models\Role` qui étendent ceux de Spatie.
-   **Noms de table :** Les tables sont préfixées par `iam_` pour éviter les conflits et clarifier leur appartenance au module d'identité.
-   **Clés primaires UUID :** La configuration `column_names.model_morph_key` est définie sur `model_id` et les migrations sont adaptées pour utiliser des UUIDs (`uuid()`, `foreignUuid()`, `uuidMorphs()`).
-   **Support des équipes (`teams`) :** La fonctionnalité `teams` est activée (`'teams' => true`), ce qui ajoute automatiquement une colonne `team_id` aux tables et relations nécessaires.

## 3. Migrations

La migration `@app-modules/iam/database/migrations/..._create_permission_tables.php` personnalise la structure de base de Spatie :

-   Toutes les clés primaires (`id`) sont des `uuid()`.
-   Les clés étrangères et les pivots sont adaptés pour utiliser des UUIDs.
-   **Table `iam_permissions` :** Ajout de colonnes `title` et `description` pour des permissions plus explicites dans une éventuelle interface de gestion.
-   **Table `iam_roles` :** Ajout d'une colonne `is_builtin` (booléen) pour identifier les rôles système, et `description` pour plus de clarté.

## 4. System Permission Discovery

The `permissions:discover` command creates the baseline permissions for all
registered module models.

-   **File:** `app-modules/iam/src/Console/Commands/DiscoverSysPermissions.php`
-   **Process:**
    1. It scans Eloquent models in `app-modules/*/src/Models`.
    2. It resolves each model through `MorphMapRegistry` and creates five
       permissions: `list`, `retrieve`, `create`, `update`, and `delete`.
    3. Permission names use the registered morph alias, for example
       `catalog_product.retrieve`, `inventory_item.update`, or
       `master_tag.delete`.
    4. Models without a registered morph alias are skipped and reported.
    5. It creates or updates the built-in Administrator and Readonly roles;
       Administrator receives all permissions and Readonly receives only
       `list` and `retrieve` permissions.

The morph namespace prevents collisions when two modules contain models with
the same basename, such as `Catalog\\Models\\Product` and
`Inventory\\Models\\Product`.

Policies should call `BasePolicy::canModel()` or `canOnModel()` so permission
names are resolved from the same morph registry. If a permission is missing,
policy authorization denies access by returning `false`.

After adding or renaming a model or morph alias, run:

```bash
docker compose exec -T app php artisan morph-map:cache --no-interaction
docker compose exec -T app php artisan permissions:discover --no-interaction
```

The command synchronizes generated permissions and built-in roles. It does not
remove permissions for models that no longer exist, so historical or custom
permissions must be reviewed separately before cleanup.

## 5. Intégration dans le Modèle Utilisateur

Le trait `Lahatre\Shared\Traits\HasAuthenticatableTraits` est appliqué à tous les modèles qui peuvent s'authentifier. Il est crucial car il :
1.  Importe `Spatie\Permission\Traits\HasRoles`.
2.  Définit `protected string $guard_name = 'sanctum';`. Cela force Spatie à utiliser le même `guard` que Sanctum, assurant que les permissions sont vérifiées contre le bon contexte d'authentification.

## 6. Middleware et Contexte d'Équipe

Pour que les permissions soient vérifiées dans le bon contexte d'équipe, le middleware `Lahatre\Iam\Http\Middleware\SetTeamPermissionsId` est utilisé.

-   Il est appliqué à chaque requête authentifiée via le groupe de middleware `auth.api` dans `bootstrap/app.php`.
-   **Rôle :** Il récupère l'équipe associée à l'utilisateur authentifié (via le `AuthContext`) et utilise la fonction `setPermissionsTeamId()` de Spatie.
-   Cela garantit que toute vérification de rôle ou de permission effectuée plus loin dans le cycle de vie de la requête (`authContext()->memberRole()->hasPermissionTo('...')`) se fera uniquement dans le périmètre de l'équipe de l'utilisateur.
