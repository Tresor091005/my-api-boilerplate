# Read contexts: reading what a write needs

Working reference for an experiment on one guinea pig. Work in progress on
the branch `t3/sale-search-permissions`, pushed as two WIP commits. The plan
below says what is done and what is left.

## Plan: what is left

State on 2026-11-16.

Done and checked, first commit: views and the `for` parameter in the package,
the table, `ReadContexts`, the guinea pig, its tests, and the documentation of
that state. In that commit the permission is swapped inside
`BasePolicy::can()`.

Started, second commit, not fully checked: step 5.

5. Services refuse another organization's record. The eight `retrieve()` are
   changed and their own tests pass (`CatalogRetrieveTenantTest`,
   `CustomerTest`). Left to do:
   - read the diff and the tests;
   - run the HTTP files that touch these services
     (`CatalogTenancyIntegrationTest`, `CatalogAuthorizationIntegrationTest`,
     `CustomerRelationsIntegrationTest`, the response selection files);
   - run PHPStan;
   - confirm that the other read methods of these services filter by
     organization.
6. The policy of the write replaces the policy of the read. The target is
   described under "Application: read contexts". To build:
   - `ReadContexts::decisionFor()` in place of `replacementFor()`;
   - a `Gate::before` hook in `SharedServiceProvider::boot()`;
   - `BasePolicy.php` back to its content on `main`;
   - the pinned test also checks that a context is never a read and that both
     names are aliases of real models;
   - tests: under a context, another organization's price level answers 404
     (today the test expects 403); a policy of creation that refuses although
     the permission is held refuses `for`; one that allows without the
     permission accepts it; `for=customer_customer.update` without that
     permission is refused.
7. Documentation of that final state: section 8 of
   `docs/iam/permissions.md`, "Views and read contexts" in
   `docs/api/response-selection.md`, and the Policies bullet of
   `.ai/rules/http-api.md` (the hook, and the rule that a line on a read by
   identifier needs a service that refuses another organization's record,
   shown by its test).

Then the points of "Left to do" below, and the owner's review.

## Problem

Many writes need another resource to exist: a customer needs a price level, a
product needs categories, unit groups and tax profiles, a price needs levels
and taxes. To fill such a field, the client must first read the other
resource, and that read asks for the other resource's own permission. So
giving someone a write also forces giving them wide reads that show far more
than the write needs.

## Idea

The client says what it is preparing: `GET /v1/catalog/price-levels?for=customer_customer.create`.
The backend knows that purpose. For such a request it does two things:

- the response is fixed to a small declared shape (a **view**), and the client
  can no longer choose fields or includes;
- the permission checked is the one of the write being prepared
  (`customer_customer.create`), in place of the normal read permission
  (`catalog_price_level.list`).

Without `for`, nothing changes: normal permission, free selection.

Two layers, kept apart:

- the **response-selection package** knows views and the `for` parameter. It
  knows nothing about permissions;
- the **application** knows read contexts: which write opens which read, as
  which view.

## Decisions

Agreed with the owner on 2026-11-15.

1. The value of `for` is the name of the write permission
   (`customer_customer.create`). No new vocabulary. A second permission that
   needs the same reads is an alias.
2. The fixed shape is a named view declared on the Resource of the module that
   owns the data. The owner of the data decides what a non-reader sees.
3. An include of another module's Resource may name a view, so the parent
   shows a simplified child. Only the guinea pig's include changes here; the
   other includes are reviewed later.
4. A view is fixed in this first version: no narrowing by the client, at the
   root or under an include. Allowing it later breaks nobody.
5. The parameter is `for`, not `context`. `context` is already
   a filter of `GET /v1/organization/exchange-rates`, a route that answers
   through response selection. In the package the words are "view" and
   "purpose" (the value of `for`); `ResponseContext` already names the
   per-request state. In the application the words are "read context".

## Package: views

`ResourceDefinition::view(string $name, string $fields, string $include = ''): self`

```php
// PriceLevelResource
$resource->view('reference', fields: 'id,name,currency_code');

// a view with children
$resource->view('sellable', fields: 'id,name',
    include: 'variants(id,name,sku,is_active),variants.prices(price_level_id,unit_code,amount)');
```

- `fields` and `include` use the client grammar of the README. A view is a
  selection written by the backend.
- A view is a white list at every level. `fields` is not empty; every include
  entry has its field list; a path prefix left without its own list is
  refused. A field added to a Resource later never enters a view by itself.
- Names are snake_case, unique per Resource (inherited views included), in
  their own name space.
- Checks. At freeze: syntax, limits and the names of the root level. The
  deeper levels need the children's definitions, so they are checked by
  `ResponseDeclarationAnalyzer::validateResource()` (cache build, field
  isolation helper) and on first use. A mistake is a programming error (a
  `LogicException`, thrown like the neighbouring declaration mistakes), never
  a 422.
- The limits of the configuration apply to what the client writes and to the
  text of each view, not to the expanded tree.

`include(..., view: 'reference')` and `virtualInclude(..., view: 'reference')`

- The child always renders that view of its Resource. The Resource's own
  routes are not affected.
- It needs one fixed child Resource: with a `ResourceResolver` the declaration
  throws. Where several Resources can render one level (a polymorphic
  include), an include fixed by a view in one and declared otherwise in
  another is a declaration mistake.
- The client selects the include bare (`include=price_level`). A field list or
  a deeper path under it answers 422 `include_is_fixed`, keyed `include`.
- Inside a view, an entry for such an include is written bare and takes the
  include's view.

How it fits the engine: views live at selection level only. After the
selection is parsed and checked, the tree is expanded: every selected include
that names a view receives the node of that view, built under its own path
(nodes are never shared between two places). This also runs inside views. The
loader and the renderer keep reading `SelectionNode`s and do not change.

The field isolation helper treats such an include as one block: it requests it
bare, does not isolate its fields and does not go below it.

## Package: the `for` parameter

```php
namespace Lahatre\ResponseSelection;

interface ViewResolver
{
    /** The view of the action's Resource that answers this purpose here, or null when it is not accepted. */
    public function viewFor(string $purpose, Request $request): ?string;
}
```

- No `ViewResolver` bound: the package ignores `for`.
- The middleware reads `for` from the query string, on an action that renders
  a Resource, when the mode is `resource`.
- 422 answers, Laravel's validation format:

  | Key | Keyed by | Raised when |
  | --- | --- | --- |
  | `for_invalid` | `for` | not a string of 1 to 100 letters, digits, `_`, `.` or `-` |
  | `for_unknown` | `for` | the resolver returns null |
  | `selection_with_for` | `fields` or `include` | sent together with `for` |
  | `selection_without_resource` | `for` | `for` sent in mode `none` |

- The resolver names a view the Resource does not declare: a programming
  error, not a 422.
- Accepted: the selection becomes the view. `ApiResponse::purpose(): ?string`
  gives the accepted value during the request, null otherwise.

The middleware runs before the organization context is resolved, so it cannot
check a permission. It fixes the shape; the application checks the right.

## Application: read contexts

`config/read-contexts.php`, the only place that says which write opens which
read:

```php
return [
    // the write being prepared => route => what it opens
    'customer_customer.create' => [
        'lahatre.catalog.price-levels.index' => ['view' => 'reference', 'replaces' => 'catalog_price_level.list'],
        'lahatre.catalog.price-levels.show'  => ['view' => 'reference', 'replaces' => 'catalog_price_level.retrieve'],
    ],
    // same reads as another context
    'customer_customer.update' => 'customer_customer.create',
];
```

`Lahatre\Shared\Policies\ReadContexts`, stateless, bound to `ViewResolver` in
`SharedServiceProvider`:

- `viewFor()`: the view of the line (purpose, route name), or null.
- `decisionFor(string $ability, array $arguments): ?bool`: the answer to one
  Gate check, or null when the check is not the one the line replaces.

This is the target of step 6 and is not built yet. Today
`ReadContexts::replacementFor()` gives `BasePolicy::can()` the permission to
check in place of the one the line replaces.

A `Gate::before` hook, registered in `SharedServiceProvider::boot()`, asks
`decisionFor()`. When `ApiResponse::purpose()` is set, and the check is the
ability and the model that the line of the current route replaces
(`catalog_price_level.list` is `list` on the model of alias
`catalog_price_level`), the read policy does not run: the policy of the write
decides.

- The write has a policy method that takes only the user (`create`): the Gate
  is asked, `Gate::allows('create', Customer::class)`, so every condition of
  that policy counts.
- The policy method needs a record (`update`), or does not exist: a lookup has
  no such record, so only the permission named by the context is checked. The
  write itself checks everything again.

`BasePolicy` is as on `main`, and no controller is touched.

Rules that hold:

- a context is a claim, never a proof. Each use needs the line, the right to
  write and the fixed view;
- with a context, the normal read permission is not looked at: someone who
  holds `catalog_price_level.list` but not `customer_customer.create` gets 403
  when sending `for`;
- the read policy is replaced only when the package accepted the purpose, so
  the shape is always fixed when it is;
- the table is pinned by a test and changes only by decision: routes are
  `GET`, the action renders through `ApiResponse` a Resource that declares the
  view, `replaces` ends with `.list` or `.retrieve`, a context is never a
  read, both names are aliases of real models, an alias points to a real
  context that is not an alias. Each new line comes with an HTTP test.

## Guinea pig: a customer and its price level

On `main` already. Creating or updating a customer sends `price_level_id`
(`CustomerRequest`). Finding it needs `catalog_price_level.list` today.

- `PriceLevelResource` declares `reference`: `id`, `name`, `currency_code`.
- The table holds the lines above.
- `CustomerResource` renders `price_level` through `reference`. This file is
  also being changed in the main checkout: it is done last, one line.

## Checks

Members hold only the permissions named.

1. `customer_customer.create`, `GET /v1/catalog/price-levels`: 403.
2. Same member, `?for=customer_customer.create`: 200, each row has exactly
   `id`, `name`, `currency_code`.
3. `for` with `fields=name`, then with `include=`: 422 on that parameter.
4. `GET /v1/catalog/products?for=customer_customer.create` (no line): 422 on
   `for`.
5. `customer_customer.retrieve` only, with `for=customer_customer.create`:
   403. `catalog_price_level.list` only, with the same `for`: 403.
6. `GET /v1/catalog/price-levels/{id}?for=customer_customer.create`: the view
   for a level of the organization, refused for another organization's.
7. `customer_customer.update` with `for=customer_customer.update`: as 2.
8. `catalog_price_level.list`, no `for`: unchanged, every field,
   `fields=name` works.
9. `GET /v1/customer/customers/{id}?include=price_level`: the child has
   exactly `id`, `name`, `currency_code`; `include=price_level(name)` is a 422.
10. The id found in 2 creates a customer: 201.

The ten checks are tests of
`tests/Feature/Integration/ReadContextIntegrationTest.php`. Two more were
added: a purpose never opens a write, and a member who also holds the normal
read permission still gets the view when sending `for`.

## Reads by identifier and the organization

Under a context the read policy does not run, so neither does its
organization check. The service is then the barrier.

- Route binding has no organization filter: it loads a record of any
  organization.
- Lists, and reads through a parent bound from the route (the variants of a
  product, the prices of an item), filter by organization in their query.
- A `retrieve()` must refuse a record of another organization itself, as
  `BundleService` does (404). Eight did not: categories, price levels,
  products, stock locations, options and customers returned the bound record
  as it is; variants and option values only checked their parent. They are
  corrected in the second WIP commit (step 5, not fully checked).
- A line on a read by identifier is added only when its service refuses
  another organization's record, shown by the line's HTTP test.

Under a context, another organization's record answers 404, from the service.
Without one it still answers 403, from the policy.

## Left to do

- `docs/api/endpoints.md`: say on the two price-level read rows that
  `?for=customer_customer.create` (or `.update`) opens them as the `reference`
  view. Not done here: the main checkout is editing those very rows.
- `CustomerResource.php` is also changed in the main checkout. Its
  `price_level` line differs here by `view: 'reference'`: a one-line merge.
- `include=x,x(name)` on an include fixed by a view is accepted as bare,
  because the parser merges the two entries before the check. Only the view
  renders, so nothing leaks. Refusing it means changing `SelectionParser`.
- PHPStan reports five errors on the whole project, all in test files this
  work did not touch (`UnitServiceTest`, `GenerationalCacheTest`,
  `TaxIncludedResolutionTest`, `CustomerPriceLevelIntegrationTest`).

## Not in this experiment

- OpenAPI description of views and of `for`.
- Narrowing inside a view.
- Rows: a view fixes columns only. Forced filters per line can come later.
- Missing searches (a variant by SKU, catalog items of every type): a context
  reopens an existing route with its filters, it adds none.
- The other includes between modules, and the lines of the sale. They follow
  once the sale slice is on `main`.

## Running things

Where this branch is the main checkout, use the project's usual commands, for
example `docker compose exec -T app php artisan test --compact <files>`. Run
targeted files: the full suite is long.

In a git worktree beside another agent that uses the main checkout:

- the worktree needs its own `vendor` (copy the main one, then
  `composer install`) and its own `.env`, with a database of its own and no
  cache or queue shared through Redis;
- every PHP command runs in a one-off container of the project image, never
  in the shared `app` container:

```bash
docker run --rm --network <compose network> --entrypoint "" -u www-data \
  -v "$WT:$WT" -v "$MAIN/.git:$MAIN/.git:ro" -w "$WT" \
  -e DB_DATABASE=<own database> lahatre-api:development <command>
```

Results so far. The package suite had 179 tests before this work and has 235,
all passing. On the application side, after step 4: the read context tests,
`tests/Feature/Architecture`, the customer, catalog authorization, price,
tenancy, IAM role and response selection files, and the shared and customer
module tests pass (270 tests), and Pint passes. After step 5 only its own
tests and the read context tests were run again (31 tests pass). The full
suite was never run.
