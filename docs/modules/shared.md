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
