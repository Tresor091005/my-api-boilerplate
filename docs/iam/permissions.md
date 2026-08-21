# Roles and Permissions with Spatie

This project uses `spatie/laravel-permission` as the foundation for a
team-aware role and permission system. This document describes the
project-specific conventions and runtime behavior.

## 1. Philosophy and goals

- **UUIDs by default:** All primary and foreign keys use UUIDs.
- **Team context:** The permission system is designed to be multi-tenant. Each
  user operates in a team context (`team_id`).
- **Built-in permissions:** The system discovers and registers system
  permissions and roles that users cannot modify, providing a stable baseline.
- **Simple naming:** Permissions follow a predictable `[model].[action]`
  convention, for example `posts.create`.

## 2. Configuration (`permission.php`)

The `@app-modules/iam/config/permission.php` configuration has been adapted
for this project:

- **Custom models:** It uses `Lahatre\Iam\Models\Permission` and
  `Lahatre\Iam\Models\Role`, which extend Spatie's models.
- **Table names:** Tables use the `iam_` prefix to avoid conflicts and make
  ownership by the identity module explicit.
- **UUID primary keys:** `column_names.model_morph_key` is `model_id`, and the
  migrations use UUID helpers such as `uuid()`, `foreignUuid()`, and
  `uuidMorphs()`.
- **Team support (`teams`):** The feature is enabled (`'teams' => true`), which
  adds a `team_id` column to the required tables and relations.

## 3. Migrations

The `@app-modules/iam/database/migrations/..._create_permission_tables.php`
migration customizes Spatie's base structure:

- All primary keys (`id`) are `uuid()`.
- Foreign keys and pivots use UUIDs.
- **`iam_permissions`:** Adds `title` and `description` for clearer
  permissions in a possible management interface.
- **`iam_roles`:** Adds `is_builtin` to identify system roles and `description`
  for clarity.

## 4. Morph Map And System Permission Discovery

Morph aliases are the stable model identifiers used by both polymorphic
relations and generated permissions. The complete lifecycle is:

1. `MorphMapRegistry` discovers concrete module models and registers aliases
   such as `catalog_product`.
2. `morph-map:cache` writes the immutable deployment cache used by normal
   application boots.
3. `permissions:discover` resolves every model through that registry and
   synchronizes the corresponding built-in permissions and roles.

The `permissions:discover` command creates the baseline permissions for all
registered module models.

-   **File:** `app-modules/iam/src/Console/Commands/DiscoverSysPermissions.php`
-   **Process:**
    1. It scans Eloquent models in `app-modules/*/src/Models`.
    2. It resolves each model through `MorphMapRegistry` and creates five
       permissions: `list`, `retrieve`, `create`, `update`, and `delete`.
    3. Permission names use the registered morph alias, for example
       `catalog_product.retrieve`, `inventory_item.update`, or
       `master_label.delete`.
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

After adding, removing, or renaming a model or morph alias, run the commands in
this order:

```bash
docker compose exec -T app php artisan morph-map:cache --no-interaction
docker compose exec -T app php artisan permissions:discover --no-interaction
```

The morph-map cache must be regenerated before permission discovery so the
permission names and policy lookups use the same aliases. A deployment should
also run the response-contract cache command when API routes or response
contracts changed:

```bash
docker compose exec -T app php artisan response-contracts:cache --no-interaction
```

The command synchronizes generated permissions and built-in roles. It does not
remove permissions for models that no longer exist, so historical or custom
permissions must be reviewed separately before cleanup.

Modules may define additional model actions through the IAM system-permissions
configuration. These actions are merged with the standard CRUD actions during
discovery. The Master notes module currently defines:

- `master_note.pin`
- `master_note.mention`
- `master_note.visibility_organization`

Note policies may intentionally bypass standard CRUD permissions for
author-owned operations while retaining these explicit permissions for
collective display and visibility actions.

## 5. User model integration

The `Lahatre\Shared\Traits\HasAuthenticatableTraits` trait is applied to all
models that can authenticate. It:

1. Imports `Spatie\Permission\Traits\HasRoles`.
2. Defines `protected string $guard_name = 'sanctum';`, forcing Spatie to use
   the same guard as Sanctum and therefore the correct authentication context.

## 6. Team-context middleware

The `Lahatre\Iam\Http\Middleware\SetTeamPermissionsId` middleware ensures
permissions are checked in the correct team context.

- It runs on every authenticated request through the `auth.api` middleware
  group in `bootstrap/app.php`.
- **Role:** It retrieves the team associated with the authenticated user through
  `AuthContext` and calls Spatie's `setPermissionsTeamId()`.
- This ensures that later role or permission checks, such as
  `authContext()->memberRole()->hasPermissionTo('...')`, remain limited to the
  user's team.
