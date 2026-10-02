# Organization module

The Organization module is intentionally small. It owns the organization
model, migration, and `OrganizationInterface` used by IAM.

It currently provides:

- `initializeOrganization(OrganizationData $data)` for creating an organization
  with its owner, immutable functional currency, timezone, and initial enabled currency;
- `findOrganizationById(string $organizationId)` for individual organization lookup.

The public `Organization` model also supports IAM's read-only
`OrganizationMember::organization()` relationship. IAM eager loads this relation
to validate contexts and list accessible member roles without individual
organization lookups. Organization creation and mutation remain owned by this module.

The IAM email-token registration cycle creates an organization together with its owner
membership and administrator role assignment. Organization settings and exchange
rates remain under `/v1/organization/*`. There is no general organization CRUD
API yet.

## Organization settings

`GET /v1/organization/settings` returns the existing settings fields together
with the organization's `name` and immutable `functional_currency_code` in the
same `data` object. Organization IDs, owner IDs, and organization timestamps are
not added to this response. The settings model's own ID and timestamps remain.

`PATCH /v1/organization/settings` accepts any subset of `name`,
`enable_currencies`, and `timezone`. Names are sanitized, non-empty strings of
at most 100 characters. The functional currency must remain enabled whenever
the currency list is supplied; omitting currencies or timezone preserves their
existing values. Other payload fields are ignored. Organization and settings
changes share a transaction and retain the existing settings update permission.

The response is `204` by default, or the combined settings resource with
`?response=resource`. These endpoints always target the active organization;
no organization deletion endpoint is exposed.
