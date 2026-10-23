# API: response selection

This document describes the response selection engine, the
`lahatre/response-selection` package in `packages/response-selection`
(namespace `Lahatre\ResponseSelection`). It lets clients choose the fields and
relations of a response, and lets Resources declare what they can render and
what each field reads. It is meant to replace the response contracts of
[Standard responses and errors](responses-and-errors.md). Its middleware runs on
every `api` route, but no action renders through it yet (section 6).

## 1. What it solves

A fixed payload over-fetches: fields nobody reads, relations loaded "just in
case", and a body after a write nobody reads. It also under-fetches: several
calls to build one screen, or the N+1 requests of a chatty API. On the server,
rendering relations row by row causes N+1 queries. Here the client names the
fields and relations it needs in one request, writes answer `204` unless a
body is requested, and the `SelectionLoader` loads each node of the selection
for all rows at once: the query count depends on the selection, not on the
number of rows.

## 2. Relation to JSON:API

`fields` and `include` follow JSON:API
[sparse fieldsets](https://jsonapi.org/format/#fetching-sparse-fieldsets) and
[inclusion of related resources](https://jsonapi.org/format/#fetching-includes):
comma-separated names, dotted include paths, and intermediate paths included
implicitly. The differences are:

- `fields` is a single list for the root resource; `fields[type]=...` is not
  supported (an array value answers 422, `invalid_type`).
- Each include carries its own field list inline: `include=addresses(id,city)`.
  Parentheses do not nest; deeper lists use dotted paths:
  `include=product(name),product.categories(name)`.
- Field and include names are the snake_case JSON keys of the response.
- Unknown field and include names are rejected with a 422 before the action
  runs, whatever the HTTP method (section 3.2).
- Under a polymorphic include, a field or include name is valid when at least
  one Resource the include may render declares it; it applies to the children
  whose Resource declares it and is ignored on the others: with
  `include=subject(options)`, variants render `options`, bundles do not. The
  includes below it are checked against the union of those Resources.
- `id` is always rendered when the Resource declares it.
- Included resources are nested under their parent's key: no top-level
  `included`, no `type`, `attributes` or `relationships` members.
- The body is plain JSON (section 3.4), not a JSON:API document.

## 3. For API clients

### 3.1 Query parameters

Selection parameters are read from the query string only, never from the body:

```text
fields    := key ("," key)*
include   := entry ("," entry)*
entry     := path [ "(" key ("," key)* ")" ]
path      := name ("." name)*
key, name := [A-Za-z_][A-Za-z0-9_]*
```

Whitespace is trimmed and empty entries are ignored. Every prefix of a path is
included with all its fields unless the prefix has its own list. A repeated
path merges its lists; a path given once without a list selects every field.
An empty list (`addresses()`) renders only `id`.

Without `fields`, the root renders every declared field. Without `include`, no
relation is rendered: relations are always opt-in.

### 3.2 Limits and errors

| `SelectionParser` constant | Value | Counts |
| --- | --- | --- |
| `MAX_DEPTH` | 6 | Segments of one include path |
| `MAX_PATHS` | 40 | Include paths, implicit prefixes included (`a.b` counts 2) |
| `MAX_FIELDS_PER_NODE` | 100 | Distinct keys in one field list |

A rejected selection answers `422` with the standard validation envelope, keyed
by `fields`, `include` or `response`, before the Form Request and the
controller run. Messages are in
`packages/response-selection/resources/lang/en/validation.php`
(`response-selection::validation.*`):

| Key | Raised when |
| --- | --- |
| `invalid_type` | `fields` or `include` is not a string |
| `invalid_characters` | A character other than letters, digits, `_ . , ( )` or spaces |
| `unbalanced_parentheses`, `nested_parentheses` | Parentheses do not pair, or nest |
| `invalid_path`, `invalid_field` | A segment or key is not an identifier, or text follows `)` |
| `fields_not_allowed_in_fields` | `fields` contains `(`, `)` or `.` |
| `depth_exceeded`, `too_many_paths`, `too_many_fields` | A limit is exceeded |
| `invalid_mode` | `response` is not `none` or `resource` |
| `mode_not_allowed` | The mode is not allowed for this method or action |
| `selection_without_resource` | `fields` or `include` is sent in mode `none` |
| `unknown_field` | A key of `fields` or of an include list is not a field of the Resource (keyed `fields` at the root, `include` below; the message gives the dotted path) |
| `unknown_include` | An include path segment is not an include of the Resource (keyed `include`, with the dotted path) |

`id` is always accepted. Field keys and include names are checked against the
definitions only: a member its `when:` refuses is still a known name, and is
then omitted from the response.

### 3.3 The `response` parameter

`ResponseMode` has two values: `none` (`204 No Content`) and `resource` (a
body). `ResolveResponseSelection` resolves them per HTTP method:

| Method | Default | Allowed |
| --- | --- | --- |
| `GET`, `HEAD` | `resource` | `resource` |
| `DELETE` | `none` | `none` |
| `POST`, `PUT`, `PATCH` | `none` | `none`, `resource` |
| Action calling only `ApiResponse::noContent()` | `none` | `none` |

`GET ...?response=none` and `DELETE ...?response=resource` answer 422
(`mode_not_allowed`). A write returns its resource only with
`?response=resource`, optionally with `fields` and `include`.

### 3.4 Body shapes

The action fixes the shape (`ResponseShape`: `item`, `list`, `cursor_page`).
Every body is wrapped in `data`:

- **item**: `{"data": {"id": "...", "name": "..."}}`;
- **list**: `{"data": [...]}`;
- **cursor page**: `{"data": [...], "meta": {"per_page", "next_cursor",
  "prev_cursor"}}`, and nothing else in `meta`.

An action may also answer `204` when it has nothing to return.

## 4. For backend developers

### 4.1 Declaring a resource

The engine renders Eloquent models only: the result of an action, every
Eloquent include and every virtual include child is a model. A result built in
code (a value object, a report) is rendered by a plain `JsonResource`, without
selection; structured data that is not a model is the array value of a
computed field.

A Resource extends `ApiResource` and implements only
`protected static function define(ResourceDefinition $resource): void`. The
engine creates the definition, passes it to `define()` once per class, then
freezes and caches it; `definition()` returns it. A subclass of another
Resource calls `parent::define($resource)` and adds to it, and calling
`definition()` from `define()` throws. A member is declared once: declaring a
field or include name again, whatever its kind and whether the first
declaration came from `parent::define()`, makes freezing throw with the
Resource and the name. A subclass adds members; it never replaces one.

The definition is the only source of rendering: `ApiResource::toArray()` is
final and generated from it. Keys render in declaration order, so the members
a subclass declares after `parent::define()` come after the parent's. There is
no `toArray()` to write, no rendering helper to call, and nothing a Resource
can add outside its definition.

Every argument is named:

```php
field(string $name, ?array $columns = null, ?array $relations = null,
    array $fields = [], string|Closure|null $computedBy = null,
    ?Closure $when = null)
include(string $name, string|ResourceResolver $resource,
    ?string $relation = null, ?Closure $when = null)
virtualInclude(string $name, string|ResourceResolver $resource,
    string|Closure $loader, bool $asList = false, array $columns = [],
    array $relations = [], array $fields = [], ?Closure $when = null)
```

- `field()`: a JSON key with what **it** reads: root `columns`, Eloquent
  `relations` paths and other `fields` of the same Resource. Declared bare (none
  of `columns`, `relations`, `fields`, `computedBy`), it reads the column of its
  key; declaring any of them lists everything it reads, and `columns: []` alone
  reads nothing. A relation path is loaded whole (`loadMissing()`); a relation
  already loaded before rendering is reused as it is, so business code that
  preloads such a relation loads it whole: no column list, no row constraint. A
  missing column then fails loudly. There is no `relation:columns` syntax.
- `include()`: an Eloquent relation rendered by an `ApiResource`, or, for a
  polymorphic relation, by the Resource a `ResourceResolver` passed as
  `resource` picks per model. `name` is the JSON key clients select; `relation`
  is the relation it loads, `Str::camel(name)` by default. The relation gives
  its cardinality and the parent keys. A filtered include (current
  organization, visible rows) is a dedicated relation of the model named by
  `relation:`, reading its context when the relation is called; when that
  context does not suit every parent, use a virtual include.
- `virtualInclude()`: child models fetched outside Eloquent relations by
  `loader`; `asList` renders a list (empty for a parent without children)
  instead of one child or `null`; `columns`, `relations` and `fields` are what
  the loader reads on the parent. `resource` accepts a `ResourceResolver`.
- An include renders a list if and only if it is a virtual include declared
  `asList`, or an Eloquent include whose relation holds a collection; otherwise
  it renders one child or `null`. Loading and rendering apply the same rule.
- A `ResourceResolver` returning `null` for a child model (no API
  representation) loads nothing under it: a single include renders `null`, a
  list include leaves the child out, so a list holds objects only.
- Two includes cannot load the same relation.
- Names are snake_case (`[a-z][a-z0-9]*(_[a-z0-9]+)*`); a name is a field or an
  include, not both. A field and an include render under their own name only,
  so `fields=` and `include=` select what the client reads:
  `include('source_location', StockLocationResource::class)` loads
  `sourceLocation()` and renders it under `source_location`.

An `ApiResource` renders only through `ApiResponse` or a parent include, which
attach its `SelectionNode`: the selected fields, `id` and the selected
includes. Rendered any other way (`new XResource($model)`, a plain
`JsonResource` parent), it throws. A Resource rendered outside `ApiResponse`
stays a `JsonResource`, and a parent migrates after its children.

#### Value of a field

- `computedBy` is a `FieldComputer` class: the value it returned for the model,
  kept in `ResponseContext`; a key it left out is omitted.
- `computedBy` is a closure `fn (Model $model, BuiltValues $built): mixed`:
  its result, computed per model.
- Neither: `getAttribute($key)` on the model (a column, a cast or an
  accessor).

A field value is data: a model, a `JsonResource` or a paginator throws (use an
include).

#### Classes and closures

`computedBy` and `loader` accept a class or a closure:

- A class (`FieldComputer`, `VirtualIncludeLoader`) receives every model of
  the node at once and may run explicit, batched queries: their number must not
  depend on the number of models.
- A closure receives one model and **must not query**: it combines what is
  already loaded or built (columns, relations declared in `relations:`, fields
  declared in `fields:`). Anything needing a query belongs in a class.

`FieldComputer::compute(Collection $models, array $fields, BuiltValues
$built)` returns the final values by model key, then by field key. `$fields`
holds the keys of this class requested at this node or required through
another member's `fields:`; only their dependencies are loaded, so computing
another key would read missing columns. `ResponseContext` remembers, per model
and class, which fields were computed: a model reached again with other fields
computes only the missing ones. A key missing for a model is omitted, so an
intended `null` is returned explicitly. Values are matched back by model key:
a class is called once per model class (models of two classes may share a
key), and a model without key reaching it throws.

Since `$fields` names exactly what is needed, a class serving several fields
computes only those: a query that aggregates per row selects only the
aggregates of `$fields`, and a service call receives only the identifiers they
need. Build any SQL fragment from a constant map keyed by field, never from the
raw string. `$fields` only ever holds keys declared with this class: unknown
requested names never reach it.

```php
private const AGGREGATES = [
    'lines_count'  => 'count(*)',
    'total_debit'  => "coalesce(sum(case when direction = 'debit' then amount else 0 end), 0)",
    'total_credit' => "coalesce(sum(case when direction = 'credit' then amount else 0 end), 0)",
];

$select = collect($fields)->map(fn (string $field) => self::AGGREGATES[$field].' as '.$field)->implode(', ');
```

`VirtualIncludeLoader::load(Collection $parents, BuiltValues $built)` returns
the children by parent key: a model or `null`, or a list of models when
`asList`; a parent left out has none, and any other value throws. It is
called once per parent class, on parents that have a key. The engine keeps the
children in `ResponseContext`, never on the model (no `setRelation()`),
renders them and loads the child nodes from them. A closure loader returns the
children of its one parent.

#### `fields:` and build order

`fields:` lists other fields of the same Resource a member needs, computed or
not. A member is built when it is requested, or when a requested member needs
it through `fields:`: it is then built but not rendered, and its columns and
relations are loaded too. Fields are built in `fields:` order, a topological
sort fixed when the definition freezes; freezing throws for a key that is not
a field, a field needing itself, and a cycle, whose message names it
(`balance → currency_code → balance`).

`fields:` is for what another builder produces. Two fields served by the same
`FieldComputer` do not declare each other: the class receives both in
`$fields` when both are needed and computes internally what they share (an
occupied volume and a fill rate from one volume query). Declaring one in the
other's `fields:` would split the work into two `compute()` calls.

Absence propagates through `fields:`: a member (field or virtual include) is
built for a model only if every member of its `fields:` exists for that model.
A member does not exist for a model when its request-only `when:` refuses, its
per-model `when:` refuses that model, its `FieldComputer` did not return its
key for that model, or one of its own dependencies does not exist for that
model. The rule is transitive and follows the build order. A member that does
not exist is omitted from the output when requested, and the model is not
passed to its computer, loader or closure. A request-only refusal propagates
before loading: the dependents of a refused member are refused too, and the
columns and relations only refused members need are not loaded; a dependency a
member that is not refused also needs stays loaded.

A builder reads the computed fields it declares through `BuiltValues`:
`get($model, $field)` returns the value for a model it received, which always
has every member of its `fields:`. Each builder receives an instance
restricted to its own `fields:`: reading another key throws, and so does
reading a field that is not computed, which the builder reads on the model
instead.

#### `when:`

`when:` on `field()`, `include()` and `virtualInclude()` decides whether the
member is built and rendered. It takes one of two closure shapes, read once
when the definition freezes (any other signature throws a
`ResponseDeclarationException`):

- `fn (Request $request): bool`, request only: evaluated once per node,
  **before loading**. When it does not return `true`, nothing is loaded or
  built for the member (its columns, its relations, the `fields:` only it
  needs, its children) nor for the members needing it through `fields:`, and
  they are omitted for every model. A dependency another requested member
  still needs is loaded and built as usual. The parameter is typed with
  `Request` itself or a type it satisfies (a parent class or an interface): the
  engine passes `app('request')`, so a `FormRequest` subclass is rejected. Prefer
  this shape for permission checks: a route permission guarding a field or an
  include costs nothing when it is refused.
- `fn (Model $model, Request $request): bool`, per model: evaluated after the
  node's columns and relations are loaded, before anything is built. A model it
  refuses is left out of the member's construction (computer, closure, loader,
  relation load) and of the loading of its children, and the member is omitted
  for it. Declare the columns it reads on the member: a missing one raises the
  missing-attribute error. An `include()` declares no columns, so its per-model
  `when:` reads only what the node loads anyway.

Only `true` accepts: a closure returning anything else refuses.

### 4.2 Writing an action

Nothing is attached to a controller: no `HasMiddleware`, no route middleware.
`ResolveResponseSelection` runs on every `api` route (`bootstrap/app.php`) and
asks `ResponseDeclarationRegistry` what the matched action renders, as read
statically from its source (section 4.4). For an action that renders through
`ApiResponse`, it resolves the mode, parses and checks `fields` and `include`,
and fills the request-scoped `ResponseContext` with the mode, the selection and
the declaration before the Form Request and the controller run, then resets it
afterwards. An action making no `ApiResponse` call is legacy: the middleware
lets it through untouched.

The analyzer imposes these rules on an action:

- its `ApiResponse` calls are written in the action method body (an inherited
  or trait method is read where it is written; a helper method or another class
  calling `ApiResponse` is not seen);
- one factory call, by name, with a literal `XResource::class`;
- a literal integer status, or a literal `possibleStatuses` list;
- `ApiResponse::noContent()`, never `response()->noContent()`, for a 204: an
  action calling only `noContent()` is declared no-content-only and accepts
  only mode `none`.

The action then makes one factory call:

- `ApiResponse::item($result, XResource::class, status, possibleStatuses)`:
  a model; `null`, a list or a paginator throws;
- `ApiResponse::list($items, XResource::class, status, possibleStatuses)`:
  models; string keys throw, integer keys are re-indexed;
- `ApiResponse::cursorPage($page, XResource::class, status, possibleStatuses)`:
  a cursor paginator of models; counts or totals over the filtered query belong
  to a dedicated endpoint;
- `ApiResponse::noContent()` may be returned beside it for an empty result.

Statuses must be 2xx with content (not 204 or 205). When the status depends on
the result, `possibleStatuses` lists every value and contains `status`.
Factories refuse Resources and anything that is not a model (array, `Fluent`,
plain object), before loading: pass the model. In mode `none` the factory
answers 204 without loading anything.

Each call is checked against the declaration in `ResponseContext`: a factory
must match its shape and Resource, and `noContent()` needs a declaration that
may answer empty (beside a factory, or alone). A mismatch, or a call without a
started context (an action a stale declaration cache lists as legacy), throws a
`ResponseSelectionException` naming the action and the expected declaration.

### 4.3 What the loader guarantees

`ApiResponse` runs `SelectionLoader::load()` before rendering. At each node:

1. request-only `when:` drop the members they refuse and the members needing
   them through `fields:`, with the fields only those need;
2. per model class, one query completes the columns that persisted models
   lack (only when some are missing), and one `loadMissing()` loads the
   relation paths of the fields to build and of the virtual includes;
3. per-model `when:` pick the models each member is built for;
4. one `loadMissing()` per model class and set of accepted models loads the
   selected Eloquent includes;
5. computed fields are built in `fields:` order, each for the models every
   member of its `fields:` exists for: a closure per model, each
   `FieldComputer` once for its fields whose dependencies are built (once per
   model class and distinct set of pending fields, usually once);
6. virtual includes are loaded the same way, once per parent class;
7. the loader recurses over the children of all accepted parents.

An include is narrowed to the child's columns and keys when it is a
`BelongsTo` or has-one/has-many relation without its own select, group or
join, has a fixed child Resource and is not the root of a `relations` path;
others load full rows. Queries are bounded by the number of nodes, never by the
number of rows, and rendering never queries.

Load and render run with `Model::preventAccessingMissingAttributes()`,
restored afterwards. Lazy loading is forbidden on every model the response
reaches through loaded relations (the models of each node, every relation
already loaded on them, by business code included, recursively, and every
virtual child), during building and rendering, in every environment; each
model's own flag is restored afterwards. The global `Model::preventLazyLoading()`
is not touched. Reading a column the definition
does not declare raises Eloquent's missing-attribute exception, and reading a
relation that was not loaded raises its lazy-loading exception instead of
querying. Locally and in tests both throw. In production, as Lahatre's
`Model::shouldBeStrict(!app()->isProduction())`, `ModelViolationReporter`
(installed by `ResponseSelectionServiceProvider`) reports each violation once per request
and model class and key, then lets it through: a missing attribute renders
`null`, and a lazy-loaded relation still queries, so its data stays right. Fix
the declaration the report points to. Without a handler, Eloquent
exempts a model that is not persisted or was created in this request
(`!exists || wasRecentlyCreated`): reading an unloaded relation on it still
lazy-loads, for example a POST with `?response=resource` rendering the model
it created. Declare the relations such a model needs in `relations:`, or load
them before calling the factory. Explicit
queries stay allowed in builder classes. A `ResponseSelectionException` is
also thrown, instead of a silent render, for:

- an `ApiResponse` call that does not match the action's declaration, or
  without one, or with a non-`ApiResource` class;
- a result that is not a model, or a virtual include child that is not a
  model (`null` aside);
- a model without key reaching a `FieldComputer` or `VirtualIncludeLoader`
  class;
- a `fields:` key that is not a field, a field needing itself, or a cycle;
- a builder reading through `BuiltValues` a key outside its `fields:`, or a
  field that is not computed;
- a selected include not loaded, or a computed field not built;
- a field value that is a model, a `JsonResource` or a paginator (use an
  include);
- a relation path continuing after a `MorphTo` (use a `FieldComputer` with
  `loadMorph()`);
- a declared name that is not a relation, or a resolver returning a class
  absent from `resources()`.

`ResponseSelectionException` and the analyzer's `ResponseDeclarationException`
extend `LogicException`, not `AssertionException`: they report a programming
error in a Resource or an action, so they surface as server errors and are
reported, instead of being rendered as a 422 business failure. Their messages
are translated (`response-selection::selection.*` and
`response-selection::declaration.*`); `context()` holds the details.

### 4.4 Declaration analyzer and registry

`ResponseDeclarationAnalyzer` parses action source code with `nikic/php-parser`
and never executes an action, a builder or a loader. `forAction($controller,
$method)` locates the method through reflection, so an inherited or trait
method is parsed in its own file (each file once), and returns `null` when the
method makes no `ApiResponse` call. Otherwise it requires the rules of section
4.2 and returns a `ResponseDeclaration`: the Resource, the shape, the statuses
and whether the action may answer empty, or `noContentOnly()`. Several factory
calls, a non-literal Resource, a non-literal status without
`possibleStatuses`, and statuses without content throw a
`ResponseDeclarationException`. For a Resource and each child it may
render, `validateResource()` checks that its definition builds and freezes
(`fields:` and `when:` included), that `computedBy` and loader classes,
resolvers and child Resources are concrete implementations of their contract,
and that every include has a child Resource. Rendering needs no check: it is
generated from the definition.

`ResponseDeclarationRegistry` maps each `Controller@method`
(`Route::getActionName()`) to its declaration, or to none; a route without a
controller action has none. `build()` analyzes every action of the router and
validates each declared Resource, so a bad declaration or definition fails
when the cache is built. `php artisan response-declarations:cache` writes
`bootstrap/cache/response-declarations.php` and
`response-declarations:clear` removes it; both are hooked into `optimize` and
`optimize:clear`. The file is read only in production, when it exists;
elsewhere each action is analyzed on its first request and memoized. An action
the cache does not list (added after it was built) is analyzed on its first
request too; an action it lists as legacy stays legacy, so after a deployment
that migrates an action, rebuild the cache or its `ApiResponse` call throws.

### 4.5 OpenAPI documentation

`ResponseSelectionOperationExtension` documents routes whose action
`ResponseDeclarationRegistry` declares. It adds the `response` parameter with its allowed values and
default, a `204` response when `none` is allowed, the `fields` and `include`
parameters listing the field keys and include paths up to
`DOCUMENTED_INCLUDE_DEPTH` (2), and one body schema per declared status and
shape, replacing the 2xx responses Scramble inferred. The resource schema is
built from the definition: every member in declaration order, fields untyped,
includes inlined up to the same depth (a virtual include as a list or as
`anyOf` the object and `null`, an Eloquent include as either, its cardinality
being the relation's), and only `id` required.

`per_page` keeps the existing rule: each Form Request validates it
(`integer`, `min:1`, `max:100`) and answers 422 outside those bounds; nothing
rewrites it before validation.

## 5. Example

How the customer Resource would be declared (not existing code:
`CustomerResource` is still a `JsonResource`):

```php
final class CustomerResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('is_active')
            ->field('currency_code', computedBy: CustomerCurrencies::class)
            ->field('balance', columns: ['balance_minor'],
                fields: ['currency_code'],
                computedBy: fn (Model $customer, BuiltValues $built): array => [
                    'amount'   => $customer->balance_minor,
                    'currency' => $built->get($customer, 'currency_code'),
                ],
                when: fn (Request $request): bool =>
                    $request->user()->can('customer.balances.view'))
            ->include('addresses', AddressResource::class)
            ->include('contacts', ContactResource::class,
                relation: 'primaryContacts');
    }
}
```

`primaryContacts()` is a relation of the model:
`$this->hasMany(Contact::class)->where('is_primary', true)`. `balance` is
built only when the user may see balances, after `currency_code`, which is
built without being rendered when only `balance` is selected. A variant's
`name` accessor reads its product and option values, so the field declares
them: `field('name', columns: ['product_id'], relations: ['product',
'optionValues.option'])`. A `show` action returns
`ApiResponse::item($customer, CustomerResource::class)`. Some requests:

```text
GET  /v1/customer/customers/{customer}?fields=name&include=addresses(city,country)
POST /v1/customer/customers?response=resource&fields=name,is_active
GET  /v1/catalog/products/{product}/variants?fields=name&include=product(name)
```

## 6. Status

The engine is a local Composer package (`packages/*` path repository, symlinked
in `vendor/lahatre/response-selection`), discovered by Laravel through its
`ResponseSelectionServiceProvider`: it scopes `ResponseContext` and
`ModelViolationReporter`, registers the registry, the
`response-declarations:cache`/`:clear` commands and their `optimize` hooks, and
the `response-selection::` translations. It depends on Laravel and
`nikic/php-parser` only; the Scramble extension needs `dedoc/scramble`. The
application attaches `ResolveResponseSelection` to its `api` group
(`bootstrap/app.php`); no action calls `ApiResponse` yet, so every route is
legacy. Remaining work:

- Add `ResponseSelectionOperationExtension` to `extensions` in
  `config/scramble.php` (empty today; `AppServiceProvider::configureScramble()`
  only adds the Sanctum scheme).
- Migrate the modules off response contracts: the
  `app-modules/<module>/config/response-contracts.php` files (customer,
  organization, catalog, library, iam, master, inventory), `ResponseResponder`
  in controllers and `applyResponseContextToQuery()` in services. The legacy
  `App\Http\Middleware\ResolveResponseContext` still runs on every `api`
  route and throws for a route without a contract, but skips an action the
  registry declares: migrating an action means replacing its response by one
  `ApiResponse` call and removing its contract.
- Then delete the legacy layer: `Lahatre\Shared\Http\Responses\*` (with its
  duplicate `ResponseMode`, `ResponseShape` and `ResponseContext`),
  `ResponseContractRegistry`, the `response-contracts:cache`/`:clear` commands
  and their `optimizes()` registration, `config/api-responses.php`, the legacy
  middleware (and its registry bridge), their translation keys and the contract
  sections of these docs.
- The package tests (`packages/response-selection/tests`) run on Orchestra
  Testbench, a bare Laravel application with an in-memory SQLite database:
  definitions, build order, `when:`, rendering, the analyzer, the registry and
  its cache, the middleware, the `ApiResponse` guards, includes and the example.
  `tests/Feature/Integration/ResponseSelectionIntegrationTest.php` covers the
  application side: the registry over every real route, the legacy middleware
  bridge and the `api` group order. Testbench brings `orchestra/canvas`, kept
  out of package discovery (`dont-discover`) so the `make:*` commands stay
  Lahatre's.
- Each migrated route gets the two checks of `Lahatre\ResponseSelection\Testing`:
  `assertBoundedQueries()` (the query count does not grow with the rows) and
  `assertFieldsRenderInIsolation()` (every field rendered alone, at every
  include up to depth 2, equals the full selection: a column or relation only a
  sibling declares fails). A root field cannot be caught this way when the
  action loads whole rows; included models are narrowed, so seed rows at every
  include. `ResponseSelectionExampleTest` runs both on example routes
  (`packages/response-selection/tests/Support/Example`).
