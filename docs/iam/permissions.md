# Gestion des Rôles et Permissions avec Spatie

Ce projet utilise le package `spatie/laravel-permission` comme base pour un système de rôles et permissions robuste et adapté aux équipes. Cette documentation explique les personnalisations et l'architecture mises en place.

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

## 4. Découverte des Permissions Système

Pour automatiser la création des permissions de base, la commande `permissions:discover` a été créée.

-   **Fichier :** `@app-modules/iam/src/Console/Commands/DiscoverSysPermissions.php`
-   **Fonctionnement :**
    1.  Elle scanne les modèles Eloquent dans tous les modules de l'application (`app-modules/*/src/Models`).
    2.  Pour chaque modèle, elle crée 5 permissions CRUD de base : `list`, `retrieve`, `create`, `update`, `delete`. Le nom est formaté en `[modèle_pluriel_snake_case].[action]`.
    3.  Elle crée/met à jour deux rôles système (`SysRole` enum) :
        -   `administrator` : Se voit attribuer toutes les permissions existantes.
        -   `default` : Se voit attribuer uniquement les permissions de lecture (`list`, `retrieve`).
-   Cette commande garantit que l'ajout d'un nouveau modèle peut rapidement être intégré au système de permissions.

## 5. Intégration dans le Modèle Utilisateur

Le trait `Lahatre\Shared\Traits\HasAuthenticatableTraits` est appliqué à tous les modèles qui peuvent s'authentifier. Il est crucial car il :
1.  Importe `Spatie\Permission\Traits\HasRoles`.
2.  Définit `protected string $guard_name = 'sanctum';`. Cela force Spatie à utiliser le même `guard` que Sanctum, assurant que les permissions sont vérifiées contre le bon contexte d'authentification.

## 6. Middleware et Contexte d'Équipe

Pour que les permissions soient vérifiées dans le bon contexte d'équipe, le middleware `Lahatre\Iam\Http\Middleware\SetTeamPermissionsId` est utilisé.

-   Il est appliqué à chaque requête authentifiée via le groupe de middleware `auth.api` dans `bootstrap/app.php`.
-   **Rôle :** Il récupère l'équipe associée à l'utilisateur authentifié (via le `AuthContext`) et utilise la fonction `setPermissionsTeamId()` de Spatie.
-   Cela garantit que toute vérification de rôle ou de permission effectuée plus loin dans le cycle de vie de la requête (`$user->can('...')`) se fera uniquement dans le périmètre de l'équipe de l'utilisateur.
