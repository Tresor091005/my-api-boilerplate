# Response selection

`lahatre/response-selection` lets API clients choose what a response contains:
nothing, a few fields, or those fields plus related records. Resources declare
what they can render and what each field reads; the engine loads exactly that,
for all rows at once.

## Why

A service keeps its own responsibility: the reason it is called. Creating an
order, confirming a payment, filtering a list, computing a total: that is its
job, and it controls what that job costs.

The trouble is what comes back afterwards. A `POST` or `PATCH` often returns
the record out of habit, although returning it is not why it was called. And
what the frontend, or any API consumer, needs changes all the time, screen by
screen. Nobody has time to write every case up front, or to be there each time
a column or a relation must be added.

So this package is first a **response selector**. The client says what it wants
back:

- **nothing**: a status code is enough (`204`), the usual case after a write;
- **just this and that**: only the fields it needs;
- **this, plus whatever a new screen suddenly needs**: a list of projects
  that, for some odd new flow, must also show when each project's owner last
  signed in (`?include=owner.sessions(last_seen_at)`). Nobody planned that jump
  from a project to a user's sign-ins, and nobody has to write an endpoint for
  it.

The backend no longer has to think about it, and the application gets faster:
nothing nobody reads is computed or sent.

It then extends to `GET` requests, to lighten the work. There too, filters,
sorting and aggregates (counts, sums) stay in the service: that is why the
service exists. The package comes **on top**, for everything outside the main
purpose of the call. It is not a query builder: clients cannot filter, sort or
aggregate through it. And no route has to use it.

Under the hood, the client names the fields and relations it needs in one
request, and the engine loads each level of the selection for all rows at once:
the query count depends on the selection, not on the number of rows, with no
N+1 queries.

## Installation

The package needs PHP 8.4, Laravel 13 and `nikic/php-parser`. Laravel discovers
`ResponseSelectionServiceProvider`. Then:

1. Add `ResolveResponseSelection` to the `api` middleware group, after
   authentication: a logged-out request then gets 401, not 422 for its
   selection, and `when:` checks can read the user:

   ```php
   // bootstrap/app.php
   $middleware->appendToGroup('api', [ResolveResponseSelection::class]);
   $middleware->appendToPriorityList(AuthenticatesRequests::class, ResolveResponseSelection::class);
   ```

2. Optionally publish the config:
   `php artisan vendor:publish --tag=response-selection-config`.
3. Optionally add `ResponseSelectionOperationExtension` to the `extensions` of
   `config/scramble.php` to document declared routes (needs `dedoc/scramble`).

`php artisan optimize` also builds the declaration cache (see
[Declaration analyzer and registry](#declaration-analyzer-and-registry)).

The middleware only acts on actions that render through `ApiResponse`. Every
other route passes through untouched, so an application can adopt the package
one action at a time.

## For API clients

### Query parameters

Selection parameters are read from the query string only, never from the body:

```text
response  := "none" | "resource"
fields    := key ("," key)*
include   := entry ("," entry)*
entry     := path [ "(" key ("," key)* ")" ]
path      := name ("." name)*
key, name := [A-Za-z_][A-Za-z0-9_]*
```

- `fields` lists the fields of the root resource. Without it, the root renders
  every declared field.
- `include` lists related resources, each with its own optional field list:
  `include=addresses(id,city)`. Without it, no relation is rendered: relations
  are always opt-in.
- Parentheses do not nest; deeper lists use dotted paths:
  `include=product(name),product.categories(name)`.
- Every prefix of a path is included with all its fields unless the prefix has
  its own list. A repeated path merges its lists; a path given once without a
  list selects every field. An empty list (`addresses()`) renders only `id`.
- Whitespace is trimmed and empty entries are ignored.
- Names are the snake_case keys of the JSON response.
- `id` is always rendered when the Resource declares it.
- Under a polymorphic include, a name is valid when at least one Resource the
  include may render declares it. It applies to the children whose Resource
  declares it and is ignored on the others: with `include=subject(options)`,
  variants render `options`, bundles do not.

Some requests:

```text
GET   /orders?fields=reference,total&include=lines(sku,quantity)
GET   /orders/7?include=lines.product(name),customer
PATCH /orders/7?response=resource&fields=status
POST  /orders
```

### The `response` parameter

| Method | Default | Allowed |
| --- | --- | --- |
| `GET`, `HEAD` | `resource` | `resource` |
| `DELETE` | `none` | `none` |
| `POST`, `PUT`, `PATCH` | `none` | `none`, `resource` |
| Action that only answers `204` | `none` | `none` |

`none` answers `204 No Content`. A write returns its resource only with
`?response=resource`, optionally with `fields` and `include`.

### Body shapes

The action fixes the shape. Every body is wrapped in `data`:

- **item**: `{"data": {"id": 7, "reference": "..."}}`;
- **list**: `{"data": [...]}`;
- **cursor page**: `{"data": [...], "meta": {"per_page", "next_cursor",
  "prev_cursor"}}`, and nothing else in `meta`.

Related resources are nested under their parent's key. An action may also
answer `204` when it has nothing to return.

### Limits

| `response-selection.limits` key | Default | Counts |
| --- | --- | --- |
| `max_depth` | 4 | Segments of one include path |
| `max_paths` | 20 | Include paths, implicit prefixes included (`a.b` counts 2) |
| `max_fields_per_node` | 50 | Distinct keys in one field list |
| `max_include_items` | 100 | Children one parent renders in a list include |

A list include renders at most `max_include_items` children per parent, or the
lower `limit:` the include declares. The cut is silent: to read a whole list,
use its own paginated endpoint. A count field next to the include, when the API
offers one, tells whether the list was cut.

### Errors

A rejected selection answers `422` with Laravel's validation format, keyed by
`fields`, `include` or `response`, before the action runs, whatever the HTTP
method. Messages come from `response-selection::validation.*`:

| Key | Raised when |
| --- | --- |
| `invalid_type` | `fields` or `include` is not a string (`fields[type]=` included) |
| `invalid_characters` | A character other than letters, digits, `_ . , ( )` or spaces |
| `unbalanced_parentheses`, `nested_parentheses` | Parentheses do not pair, or nest |
| `invalid_path`, `invalid_field` | A segment or key is not an identifier, or text follows `)` |
| `fields_not_allowed_in_fields` | `fields` contains `(`, `)` or `.` |
| `depth_exceeded`, `too_many_paths`, `too_many_fields` | A limit is exceeded |
| `invalid_mode` | `response` is not `none` or `resource` |
| `mode_not_allowed` | The mode is not allowed for this method or action |
| `selection_without_resource` | `fields` or `include` is sent in mode `none` |
| `unknown_field` | A key is not a field of the Resource (keyed `fields` at the root, `include` below; the message gives the dotted path) |
| `unknown_include` | An include path segment is not an include of the Resource (keyed `include`, with the dotted path) |

`id` is always accepted. Names are checked against the declarations only: a
member its `when:` refuses is still a known name, and is then left out of the
response.

## For backend developers

### Declaring a Resource

The engine renders Eloquent models only: the result of an action, every
Eloquent include and every virtual include child is a model. A result built in
code (a value object, a report) is rendered by a plain `JsonResource`, without
selection; structured data that is not a model is the array value of a
computed field.

A Resource extends `ApiResource` and implements only
`protected static function define(ResourceDefinition $resource): void`:

```php
final class OrderResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('reference')
            ->field('status')
            ->field('currency_code', computedBy: OrderCurrencies::class)
            ->field('total', columns: ['total_minor'],
                fields: ['currency_code'],
                computedBy: fn (Order $order, BuiltValues $built): array => [
                    'amount'   => $order->total_minor,
                    'currency' => $built->get($order, 'currency_code'),
                ],
                when: fn (Request $request): bool => $request->user()->can('orders.totals.view'))
            ->field('lines_count', columns: [], computedBy: fn (Order $order): int => $order->lines_count)
            ->include('customer', CustomerResource::class)
            ->include('lines', OrderLineResource::class, limit: 50)
            ->include('open_lines', OrderLineResource::class, relation: 'openLines');
    }
}
```

The engine calls `define()` once per class, then checks, freezes and caches the
definition; `definition()` returns it. A subclass calls
`parent::define($resource)` and adds to it; calling `definition()` from
`define()` throws. A member is declared once: declaring a name again, whatever
its kind and even from `parent::define()`, throws at freeze with the Resource
and the name. A subclass adds members; it never replaces one.

The definition is the only source of rendering: `toArray()` is final and
generated from it. Keys render in declaration order, so the members a subclass
declares after `parent::define()` come after the parent's. There is no
`toArray()` to write and nothing a Resource can render outside its definition.

Every argument is named:

```php
field(string $name, ?array $columns = null, ?array $relations = null,
    array $fields = [], string|Closure|null $computedBy = null,
    ?Closure $when = null)
include(string $name, string|ResourceResolver $resource,
    ?string $relation = null, ?int $limit = null, ?Closure $when = null)
virtualInclude(string $name, string|ResourceResolver $resource,
    string|Closure $loader, bool $asList = false, array $columns = [],
    array $relations = [], array $fields = [], ?int $limit = null,
    ?Closure $when = null)
```

- `field()`: a JSON key with what **it** reads: `columns` of the model's
  table, Eloquent `relations` paths and other `fields` of the same Resource.
  Declared bare (none of `columns`, `relations`, `fields`, `computedBy`), it
  reads the column of its key; declaring any of them lists everything it reads,
  and `columns: []` alone reads nothing. A relation path is loaded whole
  (`loadMissing()`); a relation already loaded before rendering is reused as it
  is.
- `include()`: an Eloquent relation rendered by an `ApiResource`, or, for a
  polymorphic relation, by the Resource a `ResourceResolver` passed as
  `resource` picks per model. `name` is the JSON key clients select; `relation`
  is the relation it loads, `Str::camel(name)` by default:
  `include('source_location', ...)` loads `sourceLocation()`. The relation gives
  the cardinality and the keys. A filtered include (current organization,
  visible rows) is a dedicated relation of the model, named by `relation:`.
- `virtualInclude()`: child models fetched outside Eloquent relations by
  `loader`. `asList` renders a list (empty for a parent without children)
  instead of one child or `null`; `columns`, `relations` and `fields` are what
  the loader reads on the parent.
- An include renders a list when it is a virtual include declared `asList`, or
  an Eloquent include whose relation holds a collection; otherwise it renders
  one child or `null`.
- `limit` caps the children of a list include per parent: `max_include_items`
  by default, lower when set; a higher value throws at freeze. An Eloquent
  include loads only those children (a per-parent SQL limit, the first by key
  when the relation sets no order), unless a field also reads the relation
  whole. A virtual loader should not fetch more than the limit: the rest is
  dropped.
- A `ResourceResolver` returning `null` for a child (no API representation)
  loads nothing under it: a single include renders `null`, a list include
  leaves the child out.
- Two includes cannot load the same relation. Names are snake_case
  (`[a-z][a-z0-9]*(_[a-z0-9]+)*`), and a name is a field or an include, not
  both.

An `ApiResource` renders only through `ApiResponse` or a parent include.
Rendered any other way (`new OrderResource($order)`, inside a plain
`JsonResource`), it throws: a Resource rendered outside `ApiResponse` stays a
`JsonResource`.

#### Counts, sums and other aggregates

They come from the service's query (`withCount('lines')`), not from the
Resource: the service decides what they cost. Declare the attribute as a field
that reads nothing, as `lines_count` above. Declared bare, `lines_count` would
be read as a column of the table. When the service did not add it, reading it
is reported like any missing attribute.

#### Value of a field

- `computedBy` is a `FieldComputer` class: the value it returned for the model;
  a key it left out is omitted.
- `computedBy` is a closure `fn (Model $model, BuiltValues $built): mixed`: its
  result, computed per model.
- Neither: `getAttribute($key)` on the model (a column, a cast or an
  accessor).

A field value is data: a model, a `JsonResource` or a paginator throws (use an
include).

#### Classes and closures

`computedBy` and `loader` accept a class or a closure:

- A class (`FieldComputer`, `VirtualIncludeLoader`) receives every model of the
  level at once and may run explicit, batched queries: their number must not
  depend on the number of models.
- A closure receives one model and **must not query**: it combines what is
  already loaded or built (columns, relations declared in `relations:`, fields
  declared in `fields:`). Anything needing a query belongs in a class.

`FieldComputer::compute(Collection $models, array $fields, BuiltValues $built)`
returns the values by model key, then by field key. `$fields` holds only the
keys of this class that are needed at this level, so a class serving several
fields computes only those. A key missing for a model is omitted, so an
intended `null` is returned explicitly. Values are matched back by model key: a
class is called once per model class, and a model without key reaching it
throws. A model reached again computes only the fields it still lacks.

Build any SQL fragment from a constant map keyed by field, never from the raw
string. `$fields` only ever holds keys declared with this class:

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
`asList`; a parent left out has none, and any other value throws. It is called
once per parent class. The children are kept by the engine, never set on the
model. A closure loader returns the children of its one parent.

#### `fields:` and build order

`fields:` lists other fields of the same Resource a member needs, computed or
not. A member is built when it is requested, or when a requested member needs
it: it is then built but not rendered, and its columns and relations are
loaded too. Fields are built in `fields:` order, fixed at freeze; freezing
throws for a key that is not a field, a field needing itself, and a cycle,
whose message names it (`balance → currency_code → balance`).

`fields:` is for what another builder produces. Two fields served by the same
`FieldComputer` do not declare each other: the class receives both when both
are needed and computes what they share once.

Absence follows `fields:`: a member is built for a model only if every member
of its `fields:` exists for that model. A member does not exist for a model
when its `when:` refuses, its `FieldComputer` left its key out, or one of its
own dependencies does not exist. A member that does not exist is omitted, and
the model is not passed to its builder.

A builder reads the fields it declares through `BuiltValues::get($model,
$field)`. Reading a key outside its `fields:`, or a field that is not computed
(read it on the model), throws.

#### `when:`

`when:` on `field()`, `include()` and `virtualInclude()` decides whether the
member is built and rendered. It takes one of two closure shapes, checked at
freeze:

- `fn (Request $request): bool`, request only: evaluated once per level,
  **before loading**. When it refuses, nothing is loaded or built for the
  member nor for the members needing it, and they are omitted for every model.
  Type the parameter with `Request` or a type it satisfies; a `FormRequest`
  subclass is rejected. Prefer this shape for permission checks: a refused
  member costs nothing.
- `fn (Model $model, Request $request): bool`, per model: evaluated after the
  level's columns and relations are loaded. A model it refuses is left out of
  the member's construction and loading, and the member is omitted for it.
  Declare the columns it reads on the member. An `include()` declares no
  columns, so its per-model `when:` reads only what the level loads anyway.

Only `true` accepts: anything else refuses.

### Writing an action

Nothing is attached to a controller. The middleware finds out, by reading the
action's source code without running it, which Resource the action renders.
For such an action, it resolves the mode and checks `fields` and `include`
before the Form Request and the controller run. An action making no
`ApiResponse` call is left alone.

The action follows these rules:

- its `ApiResponse` calls are written in the action method body (an inherited
  or trait method is read where it is written; a helper method or another class
  calling `ApiResponse` is not seen);
- one factory call, by name, with a literal `XResource::class`;
- a literal integer status, or a literal `possibleStatuses` list;
- `ApiResponse::noContent()`, never `response()->noContent()`, for a 204: an
  action calling only `noContent()` accepts only mode `none`.

The factory calls:

- `ApiResponse::item($model, XResource::class, status, possibleStatuses)`:
  a model; `null`, a list or a paginator throws;
- `ApiResponse::list($models, XResource::class, status, possibleStatuses)`:
  models; string keys throw, integer keys are re-indexed;
- `ApiResponse::cursorPage($page, XResource::class, status, possibleStatuses)`:
  a cursor paginator of models;
- `ApiResponse::noContent()` may be returned beside one of them for an empty
  result.

```php
public function update(UpdateOrderRequest $request, Order $order): JsonResponse|Response
{
    $order = $this->orders->update($order, $request->validated());

    return ApiResponse::item($order, OrderResource::class);
}
```

Statuses must be 2xx with content (not 204 or 205). When the status depends on
the result, `possibleStatuses` lists every value and contains `status`.
Factories refuse a Resource or anything that is not a model, before loading:
pass the model. In mode `none` the factory answers 204 without loading
anything. A call that does not match what the analyzer read throws, naming the
action and the expected declaration.

### What the loader guarantees

`ApiResponse` loads the selection before rendering. At each level:

1. request-only `when:` drop the members they refuse, and the members needing
   them;
2. per model class, one query completes the columns that saved models lack
   (only when some are missing), and one `loadMissing()` loads the relations
   the fields and virtual includes read;
3. per-model `when:` pick the models each member is built for;
4. one `loadMissing()` per model class loads the selected Eloquent includes;
5. computed fields are built in `fields:` order: a closure per model, each
   `FieldComputer` once per model class (usually once);
6. virtual includes are loaded the same way, once per parent class;
7. the loader goes down to the children of all accepted parents.

An include reads only the child's needed columns and keys when it is a
`BelongsTo`, has-one or has-many relation without its own select, group or
join, has one fixed child Resource and is not read whole by a field; others
load full rows. Queries are bounded by the number of levels, never by the
number of rows, and rendering never queries.

While loading and rendering, the engine turns on
`Model::preventAccessingMissingAttributes()` and forbids lazy loading on every
model the response reaches, then restores each flag. Reading a column the
definition does not declare, or a relation that was not loaded, throws instead
of querying. With `response-selection.tolerate_model_violations` on
(production only by default), `ModelViolationReporter` reports each violation
once per request and model, then lets it through: a missing attribute renders
`null`, a lazy-loaded relation still queries. Fix the declaration the report
points to.

Eloquent never applies these checks to a model that is not saved or was
created in this request: reading an unloaded relation on it still queries, for
example a `POST` with `?response=resource`. Declare the relations such a model
needs in `relations:`, or load them before calling the factory.

A `ResponseSelectionException` is thrown, instead of a silent render, for:

- an `ApiResponse` call that does not match the action, or a class that is not
  an `ApiResource`;
- a result or virtual include child that is not a model (`null` aside);
- a model without key reaching a `FieldComputer` or `VirtualIncludeLoader`;
- a `fields:` mistake (unknown key, a field needing itself, a cycle);
- a builder reading a key outside its `fields:`;
- a selected include not loaded, or a computed field not built;
- a field value that is a model, a `JsonResource` or a paginator;
- a relation path continuing after a `MorphTo` (use a `FieldComputer` with
  `loadMorph()`);
- a declared name that is not a relation, or a resolver returning a class
  absent from its `resources()`.

`ResponseSelectionException` and `ResponseDeclarationException` extend
`LogicException`: they report a programming error, so they surface as server
errors and are reported. Their messages are translated
(`response-selection::selection.*`, `response-selection::declaration.*`), and
`context()` holds the details.

### Declaration analyzer and registry

`ResponseDeclarationAnalyzer` parses action source code with `nikic/php-parser`
and never runs an action, a builder or a loader. It returns `null` for an
action without `ApiResponse` call, or the Resource, the shape, the statuses and
whether the action may answer empty. A broken rule of
[Writing an action](#writing-an-action) throws a `ResponseDeclarationException`.
For each Resource and each child it may render, it also checks that the
definition freezes and that every class it names exists and implements its
contract.

`ResponseDeclarationRegistry` maps each `Controller@method` to its declaration.
`php artisan response-declarations:cache` analyzes every route and writes
`bootstrap/cache/response-declarations.php`, so a bad declaration fails when
the cache is built; `response-declarations:clear` removes it. Both run with
`optimize` and `optimize:clear`. The file is read only in production; elsewhere
each action is analyzed on its first request. After a deployment that changes
what an action renders, rebuild the cache, or its `ApiResponse` call throws.

### OpenAPI documentation

`ResponseSelectionOperationExtension`, a Scramble extension, documents each
declared route: the `response` parameter with its allowed values and default, a
`204` response when `none` is allowed, the `fields` and `include` parameters
with the names a client can use (include paths up to
`response-selection.documented_include_depth`, 2 by default), and one body
schema per status and shape. The schema lists every member in declaration
order, inlines includes up to the same depth with their `limit` as `maxItems`,
and marks only `id` required. Field values are not typed.

### Testing a route

`Lahatre\ResponseSelection\Testing` gives two checks for each route:

- `assertBoundedQueries($seed, $request)` (`AssertsBoundedQueries`): the query
  count does not grow with the number of rows;
- `assertFieldsRenderInIsolation($uri, XResource::class)`
  (`AssertsResponseFieldIsolation`): every field requested alone, at every
  include up to depth 2, renders the same value as in the full response, so a
  column or relation only a sibling field declares fails. Seed rows at every
  include.

### Package layout

The root of `src/` holds what an application uses: `ApiResource`,
`ApiResponse`, `ResourceDefinition`, the builder contracts (`FieldComputer`,
`VirtualIncludeLoader`, `ResourceResolver`, `BuiltValues`) and the
`ResolveResponseSelection` middleware. The internals sit in `Selection/`
(reading and checking `fields` and `include`), `Declaration/` (what each action
renders), `Rendering/` (loading, computing, per-request state), `Console/`,
`Scramble/` and `Testing/`.

The package tests run on Orchestra Testbench with an in-memory SQLite
database.
