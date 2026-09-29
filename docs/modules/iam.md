# IAM module

The IAM module owns authentication and organization-scoped authorization.

## Authentication flow

1. A user registers or logs in through `/v1/auth/*`.
2. Sanctum issues a personal access token using the custom
   `Lahatre\Iam\Auth\PersonalAccessToken` model.
3. The token metadata records the selected organization/member-role context.
4. `ResolveAuthContext` validates that metadata against the authenticated user
   and loads the organization, membership, member role, and role.
5. `SetTeamPermissionsId` sets Spatie's team ID before permission checks.

An incoherent or missing organization context is rejected on routes that use
`auth.api`. A plain `auth:sanctum` route can authenticate a user without
establishing an organization context.

## Current operations

The module supports registration, login, logout, current-user retrieval,
member-role switching, current-permission retrieval, permission catalog listing,
forgot-password, and reset-password. `GET /v1/iam/permissions` lists all
permissions for the active guard, including those not assigned to the current
role. It requires an active organization role with `iam_permission.list` and has
no mutation or detail routes. Member administration does not yet have a
dedicated API.

`/v1/iam/roles` supports listing, retrieving, creating, updating, and deleting
roles. Reads include global built-in roles and roles belonging to the current
organization. The API creates roles with the current organization's `team_id`,
`is_builtin = false`, and the active guard; clients cannot set those fields.
Only roles belonging to the current organization can be changed, and built-in
roles are immutable. A role with an active member-role assignment cannot be
deleted. Otherwise deletion is soft, retaining the role and its permission
assignments in storage while excluding it from normal reads. `response-contracts.php`
loads role permissions when `include=permissions` is requested. Deletion uses a scoped database
query and explicitly clears Spatie's permission cache.

`permissions:discover` scans direct PHP files under each module's
`src/Models` directory, keeps only classes that extend Eloquent's `Model`, and
creates the CRUD permissions `list`, `retrieve`, `create`, `update`, and
`delete`. It also synchronizes the built-in Administrator and Readonly roles and
clears the Spatie permission cache before and after the operation. It does not
remove permissions for models that no longer exist.

The module policies use the shared `BasePolicy`. Collection actions check a
permission globally; model actions additionally require the model's
`organization_id` to match the current Spatie team ID. Restore and force-delete
are explicitly denied by the catalog policies, while master currencies are
read-only and units expose a dedicated `upsert` policy operation. Inventory stock
updates use the same organization-scoped model check.

The root `DatabaseSeeder` is idempotent for the development administrator and
organization, discovers permissions, seeds catalog reference data, and assigns
each built-in system role to the member. It is not a production provisioning
workflow.

## Boundaries and gaps

- Email verification middleware is not currently enabled on role switching.
- The password reset flow uses Laravel's password broker to create a token and
  returns an application reset URL in the JSON response. It does not send an
  email and does not define a custom notification class.
- The Horizon/Telescope gates contain no configured production allow-list yet;
  production access must be explicitly configured before exposing those UIs.
