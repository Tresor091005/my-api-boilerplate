# Shared module

The Shared module contains infrastructure used by multiple modules and owns no
business aggregate.

It provides:

- `AssertionException` and shared exception contracts;
- UUID/factory traits and the shared authenticatable model;
- deterministic cursor pagination;
- handle and SKU generation;
- model discovery and morph-map caching;
- response-contract discovery and deployment caching;
- PostgreSQL-backed, organization-scoped business numbering;
- module-aware/native Artisan generator overrides;
- generator architecture support and shared test helpers.

Shared code must remain independent of business modules. If a business rule is
added here, it should first be expressed as a public contract or moved to the
owning module.

## Generated identifiers

`HandleGenerator` creates a slug and chooses the next numeric suffix among
matching rows. It is a naming helper, not a concurrency guarantee: the owning
table must still enforce the relevant uniqueness constraint and race-safe write
path. `SkuGenerator` creates a readable date/random SKU and is likewise
unique-ish rather than a database identity mechanism.

## Business numbering

`BusinessNumberService::next($key)` generates a unique, human-readable sequence
for the active organization. Definitions live in
`shared/config/business-numbering.php`. The format supports only the system
tokens `{YEAR}`, `{YEAR2}`, `{MONTH}`, `{DAY}`, and `{SEQ}`. There are no
runtime placeholders or hidden scopes. The organization is always resolved by
`currentOrganizationId()` and is never accepted as an argument.

The service writes `shared_business_number_counters`, which makes the Shared
module's ownership explicit. It increments the counter with one PostgreSQL
`INSERT ... ON CONFLICT DO UPDATE ... RETURNING` statement. Before incrementing,
it renders the configured format with the current date, using `0` in place of
`{SEQ}`. This rendered value is stored as
`number_identity`, and its SHA-256 hash is indexed with `organization_id`.
Different keys that render the same number identity intentionally share one
counter. A format change creates a different sequence, while restoring a prior
format resumes its previous sequence.

The reset period is encoded in the visible format. `yearly` requires `{YEAR}`
or `{YEAR2}`, `monthly` also requires `{MONTH}`, and `daily` also requires
`{DAY}`. Additional date tokens are allowed and become part of the visible
number identity. This prevents a reset from producing a number that has already
been displayed.

The statement uses the current Laravel connection and therefore participates in
an existing transaction. A number consumed outside the surrounding business
transaction can leave a gap when the business operation later fails; this
primitive guarantees atomicity and uniqueness, not legal gapless numbering.
