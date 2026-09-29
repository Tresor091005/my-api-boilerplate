# Organization module

The Organization module is intentionally small. It owns the organization
model, migration, and `OrganizationInterface` used by IAM.

It currently provides:

- `initializeOrganization(OrganizationData $data)` for creating an organization
  with its owner, immutable functional currency, timezone, and initial enabled currency;
- `findOrganizationById(string $organizationId)` for authenticated context
  resolution.

The IAM email-token registration cycle creates an organization together with its owner
membership and administrator role assignment. Organization settings and exchange
rates remain under `/v1/organization/*`. There is no general organization CRUD
API yet.
