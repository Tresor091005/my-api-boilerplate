# API: response selection

Clients choose what a response contains (`?response=`, `?fields=`,
`?include=`) through the `lahatre/response-selection` package in
`packages/response-selection` (namespace `Lahatre\ResponseSelection`). What it
is, why it exists and how to use it, for API clients and backend developers:
[the package README](../../packages/response-selection/README.md).

This page covers only how Lahatre uses it. The package is meant to replace the
response contracts of [Standard responses and errors](responses-and-errors.md).
The catalog module answers through it; the other modules still use response
contracts.

## Wiring

- The package is a local Composer package (`packages/*` path repository,
  symlinked in `vendor/lahatre/response-selection`), discovered through its
  `ResponseSelectionServiceProvider`.
- `bootstrap/app.php` adds `ResolveResponseSelection` to the `api` group, after
  the legacy `ResolveResponseContext`, and orders it after authentication: a
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
`$this->hasMany(Contact::class)->where('is_primary', true)`. A variant's `name`
accessor reads its product and option values, so the field declares them:
`field('name', columns: ['product_id'], relations: ['product',
'optionValues.option'])`. Some requests:

```text
GET  /v1/customer/customers/{customer}?fields=name&include=addresses(city,country)
POST /v1/customer/customers?response=resource&fields=name,is_active
GET  /v1/catalog/products/{product}/variants?fields=name&include=product(name)
```

## Status and remaining work

Catalog is migrated: every catalog action ends with an `ApiResponse` call, its
services load nothing for the response, and its contract file keeps only the
two file download routes, which do not answer JSON. Its Resources render the
children of other modules through `UnitGroupApiResource`, `UnitApiResource`,
`LabelApiResource`, `AddressApiResource` (master), `InventoryItemApiResource`
(inventory) and `FileAttachmentApiResource` (library), which sit beside the
legacy Resources of those modules until they migrate.
`ResponseSelectionOperationExtension` is registered in `config/scramble.php`,
so the API documentation describes `response`, `fields`, `include` and the
body of every route that answers through `ApiResponse`. Product and service
files are the `main_files` and `gallery_files` includes; each attachment shows
its file's values beside its own (the `DescribesFile` trait lists them once for
every library response), and the product
`options` and category `bloodline` fields render only when listed in `fields`.

Remaining work:

- Migrate the modules off response contracts: the
  `app-modules/<module>/config/response-contracts.php` files (customer,
  organization, library, iam, master, inventory), `ResponseResponder`
  in controllers and `applyResponseContextToQuery()` in services. The legacy
  `App\Http\Middleware\ResolveResponseContext` still runs on every `api` route
  and throws for a route without a contract, but skips an action the registry
  declares: migrating an action means replacing its response by one
  `ApiResponse` call and removing its contract.
- Then delete the legacy layer: `Lahatre\Shared\Http\Responses\*` (with its
  duplicate `ResponseMode`, `ResponseShape` and `ResponseContext`),
  `ResponseContractRegistry`, the `response-contracts:cache`/`:clear` commands
  and their `optimizes()` registration, `config/api-responses.php`, the legacy
  middleware (and its registry bridge), their translation keys and the contract
  sections of these docs.
- Give each migrated route the two checks of `Lahatre\ResponseSelection\Testing`
  (`assertBoundedQueries()` and `assertFieldsRenderInIsolation()`).

## Tests

- `packages/response-selection/tests`: the package suite, on Orchestra
  Testbench with an in-memory SQLite database. Run it with
  `php artisan test --compact packages/response-selection/tests`. Testbench
  brings `orchestra/canvas`, kept out of package discovery (`dont-discover`) so
  the `make:*` commands stay Lahatre's.
- `tests/Feature/Integration/ResponseSelectionIntegrationTest.php`: the
  application side, that is the registry over every real route, the legacy
  middleware bridge and the `api` group order.

Where the engine spends time and what to optimize:
[performance review](../decisions/response-selection-performance.md).
