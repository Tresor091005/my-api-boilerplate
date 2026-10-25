# API: response selection

Clients choose what a response contains (`?response=`, `?fields=`,
`?include=`) through the `lahatre/response-selection` package in
`packages/response-selection` (namespace `Lahatre\ResponseSelection`). What it
is, why it exists and how to use it, for API clients and backend developers:
[the package README](../../packages/response-selection/README.md).

This page covers only how Lahatre uses it. Every route rendering a model
answers through it; the few routes that render none are listed under
[Status](#status).

## Wiring

- The package is a local Composer package (`packages/*` path repository,
  symlinked in `vendor/lahatre/response-selection`), discovered through its
  `ResponseSelectionServiceProvider`.
- `bootstrap/app.php` adds `ResolveResponseSelection` to the `api` group, after
  the other `api` middleware, and orders it after authentication: a
  logged-out request gets 401, not a 422 for its selection.
- `response-selection.tolerate_model_violations` is left at its default
  (production only), which matches `Model::shouldBeStrict(!app()->isProduction())`
  in `AppServiceProvider`.
- Its exceptions extend `LogicException`, not `AssertionException`: they report
  a programming error, so they surface as 500 and are reported, never rendered
  as a 422 business failure.
- `per_page` keeps the existing rule: each Form Request validates it
  (`integer`, `min:1`, `max:100`).

## Example

A customer Resource with a computed balance (illustrative: the real
`CustomerResource` has no balance):

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
`$this->hasMany(Contact::class)->where('is_primary', true)`. A variant's `name`
accessor reads its product and option values, so the field declares them:
`field('name', columns: ['product_id'], relations: ['product',
'optionValues.option'])`. Some requests:

```text
GET  /v1/customer/customers/{customer}?fields=name&include=addresses(city,country)
POST /v1/customer/customers?response=resource&fields=name,is_active
GET  /v1/catalog/products/{product}/variants?fields=name&include=product(name)
```

## Status

Every module answers through it (catalog, customer, inventory, library,
master, iam and organization): their actions end with an `ApiResponse` call, their
services load nothing for the response, and a Resource renders another
module's records through that module's own Resource (`UnitGroupResource`,
`AddressResource`, `InventoryItemResource`, `FileAttachmentApiResource`,
...).
`ResponseSelectionOperationExtension` is registered in `config/scramble.php`,
so the API documentation describes `response`, `fields`, `include` and the
body of every route that answers through `ApiResponse`. Product and service
files are the `main_files` and `gallery_files` includes; each attachment shows
its file's values beside its own (the `DescribesFile` trait lists them once for
every library response), and the product
`options` and category `bloodline` fields render only when listed in `fields`.

Routes that render no model answer without response selection: their
controller builds the JSON (`response()->json()` or a `JsonResource`'s
`response()`), always with a body, or streams a file.

- the file content downloads (catalog products and services, customers,
  library), which stream the file;
- the IAM sign-in payload (`access_token`, `token_type`, `user`) of the email
  and Google verifications and the invitation acceptances, and the IAM
  confirmation messages and challenges;
- the inventory lots per location and stock summary, which are computed rows.

## Tests

- `packages/response-selection/tests`: the package suite, on Orchestra
  Testbench with an in-memory SQLite database. Run it with
  `php artisan test --compact packages/response-selection/tests`. Testbench
  brings `orchestra/canvas`, kept out of package discovery (`dont-discover`) so
  the `make:*` commands stay Lahatre's.
- `tests/Feature/Integration/ResponseSelectionIntegrationTest.php`: the
  application side, that is the registry over every real route, the `api`
  group order and the OpenAPI parameters.
- `tests/Feature/Integration/<Module>ResponseSelectionIntegrationTest.php`:
  each module's lists under `assertBoundedQueries()` and
  `assertFieldsRenderInIsolation()`.
