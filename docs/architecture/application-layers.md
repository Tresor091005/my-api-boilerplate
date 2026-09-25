# Application layer patterns

The codebase separates transport, authorization, orchestration, persistence,
and representation. The separation is visible in every module even when a
small feature does not need every class.

## HTTP flow

```text
Route → middleware → FormRequest → Controller → Policy/Gate → Data → Service
     → model/query → Resource/ViewData → JSON response
```

- Routes define prefixes, names, middleware, and nested scoping.
- Form Requests validate and normalize HTTP input.
  Library provides reusable requests for a single file ID, a list of file IDs,
  and a list of attachment IDs. A consuming module may extend them when its
  HTTP input needs additional validation. The service decides whether an
  operation replaces, adds, reorders, or removes attachments.
- Controllers authorize the HTTP action and delegate orchestration.
- Data classes are immutable typed service inputs; they do not know HTTP,
  Eloquent, authorization, or tenant context.
- Services own business orchestration and transaction boundaries.
- Cross-module callers use the owning module's public contract. For file links,
  Catalog and Customer inject `LibraryInterface`; `LibraryService` delegates
  attachment persistence to Library's internal `AttachmentService`.
  Each parent model implements `HasFileSlots` to declare its slots, maximum
  counts, and MIME types; `InteractsWithFile` provides the attachment relation.
  Catalog uses `main` and `gallery`; Customer uses `profile_picture`. Library
  enforces those slot rules, tenant ownership, and link integrity. Catalog and
  Customer retain their parent-state checks.
  `AttachmentService` owns the attachment transaction; `LibraryService` delegates
  without opening another one.
- Models and query services own persistence and relation loading.
- API Resources represent model-backed responses; ViewData represents computed
  or aggregate projections.

## Authorization and tenancy

Authorization belongs at the HTTP boundary through Policies/Gates. Services
still enforce ownership and invariant checks because they are also callable by
commands, jobs, schedulers, and other application entry points.
Library file creation and direct retrieval use `FilePolicy`. Linking an existing
file through Catalog or Customer uses that parent's update permission; linked
content uses its retrieve permission.

Inventory and catalog data use explicit organization boundaries. The selected
organization comes from `AuthContext`; it is never inferred from an arbitrary
request field. Soft-deletable models rely on Eloquent's normal scope, while raw
queries and aggregates must state their soft-delete boundary explicitly.

## Transactions

Mutation services own `DB::transaction()` calls. A controller should not wrap a
service call in a second transaction merely for consistency. Effects that
depend on committed state use `DB::afterCommit()`; the unit cache rewarm is the
current example.

Inventory mutations additionally use row locks and idempotency checks. A
preview executes the validation/calculation path and rolls back without
persisting ledger changes.

## Query and response conventions

Lists use typed filter Data objects and deterministic cursor pagination. The
shared cursor helper requires `sortBy`, `sortOrder`, `perPage`, and `cursor`.
It clamps page sizes to 1–100, accepts only ascending or descending sort
directions, and always places a stable tie-breaker last in the ordering.

Services return models, Collections, paginators, or ViewData. Controllers wrap
those results in Resources or Resource Collections for HTTP responses. Resources
should use conditional relation loading (`whenLoaded`) so an endpoint does not
accidentally expand its query contract.
