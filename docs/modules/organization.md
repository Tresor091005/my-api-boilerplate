# Organization module

The Organization module is intentionally small. It owns the organization
model, migration, and `OrganizationInterface` used by IAM.

It currently provides:

- `initializeOrganization(array $data)` for creating an organization;
- `findOrganizationById(string $organizationId)` for authenticated context
  resolution.

There are no public organization CRUD routes yet. Membership and role context
are owned by IAM and must go through IAM's public contract rather than reaching
into organization internals from another module.
